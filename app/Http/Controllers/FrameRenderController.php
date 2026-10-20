<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\LocalGuide;
use App\Services\Frames\FrameRenderer;
use App\Support\FramePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal, signed render page (admin live preview + server-side renderer).
 */
class FrameRenderController extends Controller
{
    public function __invoke(Request $request, Frame $frame): Response
    {
        $guide = $request->filled('guide') ? LocalGuide::where('contrib_id', $request->query('guide'))->first() : null;

        $stats = $guide
            ? [...$guide->statsForFrame(), 'views_per_photo' => $guide->viewsPerPhoto()]
            : FrameRenderer::SAMPLE_STATS;

        // Only same-origin, signed upload URLs may be passed as photos.
        $photos = collect((array) $request->query('photos', []))
            ->map(fn ($p) => is_string($p) && str_starts_with($p, url('/uploads/')) ? $p : null)
            ->all();

        return Inertia::render('frames/render', [
            'frame' => FramePresenter::editor($frame),
            'stats' => $stats,
            'photos' => $photos,
        ]);
    }
}
