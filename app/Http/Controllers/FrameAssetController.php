<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves frame artwork from storage through the app origin, so the editor's
 * canvas can export without cross-origin tainting (no S3 CORS needed).
 * URLs carry a content hash (?v=) so they can be cached aggressively.
 */
class FrameAssetController extends Controller
{
    public function __invoke(Frame $frame, string $kind): StreamedResponse
    {
        $path = match ($kind) {
            'background' => $frame->background_path,
            'overlay' => $frame->overlay_path,
            'thumbnail' => $frame->thumbnail_path ?? $frame->background_path,
            default => null,
        };

        abort_if($path === null || ! Storage::exists($path), 404);

        return Storage::response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
