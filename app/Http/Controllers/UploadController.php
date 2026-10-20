<?php

namespace App\Http\Controllers;

use App\Services\Images\ImageNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 2 of the editor: photo uploads. Files are re-encoded server-side and
 * stored privately under tmp/{user}/ until a generation is created.
 */
class UploadController extends Controller
{
    public const TEMP_URL_MINUTES = 120;

    public function store(Request $request, ImageNormalizer $normalizer): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('photo');
        if (! $normalizer->isAllowed($file)) {
            throw ValidationException::withMessages(['photo' => __('editor.photos.bad_type')]);
        }

        try {
            $result = $normalizer->normalize($file->getRealPath());
        } catch (\RuntimeException) {
            throw ValidationException::withMessages(['photo' => __('editor.photos.bad_type')]);
        }

        $path = sprintf('tmp/%d/%s.jpg', $request->user()->id, Str::ulid()->toBase32());
        Storage::put($path, $result['binary'], ['visibility' => 'private', 'ContentType' => 'image/jpeg']);

        return response()->json([
            'path' => $path,
            'url' => self::temporaryUrl($path),
            'width' => $result['width'],
            'height' => $result['height'],
        ], 201);
    }

    /** Signed, expiring, same-origin URL (keeps the Konva canvas untainted). */
    public static function temporaryUrl(string $path): string
    {
        return URL::temporarySignedRoute('uploads.show', now()->addMinutes(self::TEMP_URL_MINUTES), ['path' => $path]);
    }

    public function show(string $path): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'tmp/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::exists($path), 404);

        return Storage::response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
