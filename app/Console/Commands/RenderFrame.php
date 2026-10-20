<?php

namespace App\Console\Commands;

use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Services\Frames\FrameRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side rendering (FEATURE_SERVER_RENDER=true):
 *   frames:render <frame-slug> [contrib_id] --out=path.png   → render to a file
 *   frames:render --regenerate=<generation-ulid>             → re-render an OG image
 *                                                              with current stats
 */
class RenderFrame extends Command
{
    protected $signature = 'frames:render {frame? : Frame slug} {contrib_id? : Use this guide\'s stats (default: sample stats)}
        {--out= : Output PNG path} {--regenerate= : Re-render an existing generation with its guide\'s current stats (placeholder photos)}';

    protected $description = 'Render a frame with headless Chromium (optional server-side renderer)';

    public function handle(FrameRenderer $renderer): int
    {
        if (! FrameRenderer::enabled()) {
            $this->error('Server-side rendering is disabled. Set FEATURE_SERVER_RENDER=true.');

            return self::FAILURE;
        }

        if ($ulid = $this->option('regenerate')) {
            $generation = FrameGeneration::with(['frame', 'localGuide'])->findOrFail($ulid);
            $png = $renderer->render($generation->frame, $generation->localGuide);
            Storage::put($generation->output_path, $png, ['visibility' => 'public', 'ContentType' => 'image/png']);
            $this->info("Re-rendered {$generation->output_path}");

            return self::SUCCESS;
        }

        $frame = Frame::where('slug', (string) $this->argument('frame'))->firstOrFail();
        $guide = $this->argument('contrib_id') ? LocalGuide::where('contrib_id', $this->argument('contrib_id'))->firstOrFail() : null;

        $started = microtime(true);
        $png = $renderer->render($frame, $guide);
        $out = $this->option('out') ?: storage_path("app/private/renders/{$frame->slug}.png");
        File::ensureDirectoryExists(dirname($out));
        File::put($out, $png);

        $size = getimagesizefromstring($png);
        $this->info(sprintf('Rendered %dx%d PNG in %.1fs → %s', $size[0] ?? 0, $size[1] ?? 0, microtime(true) - $started, $out));

        return self::SUCCESS;
    }
}
