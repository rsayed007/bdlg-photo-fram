<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Support\FramePresenter;
use App\Support\GuidePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FrameGenerationController extends Controller
{
    /** Step 4: store the client-rendered PNG and create the share record. */
    public function store(Request $request, Frame $frame): JsonResponse
    {
        abort_unless($frame->is_active || $request->user()->isAdmin(), 404);

        $user = $request->user();
        $data = $request->validate([
            'image' => ['required', 'file', 'max:20480'],
            'local_guide_id' => ['required', 'integer', Rule::exists('local_guides', 'id')],
            'sources' => ['array', 'max:'.count($frame->config['photo_slots'] ?? [])],
            'sources.*' => ['nullable', 'string', 'max:200'],
        ]);

        /** @var LocalGuide $guide */
        $guide = LocalGuide::findOrFail($data['local_guide_id']);
        if ($guide->user_id !== $user->id && ! $user->isAdmin()) {
            throw ValidationException::withMessages(['local_guide_id' => 'You can only create frames for your own profile.']);
        }

        // Final image must be a real PNG at the frame's native size.
        $file = $request->file('image');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $size = @getimagesize($file->getRealPath());
        if ($mime !== 'image/png' || ! $size || $size[0] !== $frame->size_w || $size[1] !== $frame->size_h) {
            throw ValidationException::withMessages(['image' => "The image must be a {$frame->size_w}×{$frame->size_h} PNG."]);
        }

        // Only the member's own temp uploads may be attached.
        $prefix = "tmp/{$user->id}/";
        /** @var list<string|null> $sourceInput */
        $sourceInput = $data['sources'] ?? [];
        $sources = collect($sourceInput)->filter()->values();
        foreach ($sources as $path) {
            if (! str_starts_with($path, $prefix) || str_contains($path, '..') || ! Storage::exists($path)) {
                throw ValidationException::withMessages(['sources' => 'Invalid photo reference.']);
            }
        }

        $generation = new FrameGeneration;
        $generation->id = (string) Str::ulid();

        $output = "public/generations/{$generation->id}.png";
        Storage::putFileAs('public/generations', $file, "{$generation->id}.png", ['visibility' => 'public', 'ContentType' => 'image/png']);

        // Keep originals (private) for 30 days, then frames:purge-sources removes them.
        $kept = $sources->map(function (string $path, int $i) use ($generation) {
            $target = "originals/{$generation->id}/{$i}.jpg";
            Storage::move($path, $target);

            return $target;
        })->all();

        $generation->fill([
            'frame_id' => $frame->id,
            'local_guide_id' => $guide->id,
            'user_id' => $user->id,
            'output_path' => $output,
            'stats_snapshot' => [
                ...$guide->statsForFrame(),
                'synced_at' => $guide->last_synced_at?->toIso8601String(),
            ],
            'source_photo_paths' => $kept,
        ]);
        $generation->save();

        return response()->json([
            'id' => $generation->id,
            'share_url' => $generation->shareUrl(),
            'image_url' => $generation->imageUrl(),
        ], 201);
    }

    public function destroy(Request $request, FrameGeneration $generation): RedirectResponse
    {
        Gate::authorize('delete', $generation);

        $generation->delete();

        return redirect()->route('dashboard')->with('success', __('mine.delete'));
    }

    /** The member's dashboard: their profiles and generated frames. */
    public function mine(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'guides' => $user->localGuides()->get()->map(fn (LocalGuide $g) => GuidePresenter::summary($g)),
            'generations' => $user->frameGenerations()
                ->with(['frame:id,title,slug', 'localGuide:id,name'])
                ->latest('created_at')
                ->get()
                ->map(fn (FrameGeneration $g) => FramePresenter::generationCard($g)),
        ]);
    }
}
