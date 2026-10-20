<?php

namespace App\Http\Controllers;

use App\Enums\GuideStatus;
use App\Models\Frame;
use App\Models\LocalGuide;
use App\Support\FramePresenter;
use App\Support\GuidePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FrameController extends Controller
{
    public function index(): Response
    {
        $frames = Frame::active()->with('event')->get();

        return Inertia::render('frames/index', [
            'groups' => $frames->groupBy(fn (Frame $f) => $f->event?->name ?? '')
                ->map(fn ($group, $event) => [
                    'event' => $event !== '' ? $event : null,
                    'description' => $group->first()?->event?->description,
                    'frames' => $group->map(fn (Frame $f) => FramePresenter::card($f))->values(),
                ])
                ->values(),
            'meta' => [
                'title' => __('frames.title').' — '.__('site.name'),
                'description' => __('frames.subtitle'),
            ],
        ]);
    }

    public function create(Request $request, Frame $frame): Response
    {
        abort_unless($frame->is_active || $request->user()?->isAdmin(), 404);

        $guides = LocalGuide::where('user_id', $request->user()->id)
            ->where('status', '!=', GuideStatus::Inactive)
            ->orderByDesc('points')
            ->get()
            ->map(fn (LocalGuide $g) => GuidePresenter::summary($g));

        return Inertia::render('frames/create', [
            'frame' => FramePresenter::editor($frame->load('event')),
            'guides' => $guides,
            'staleAfterHours' => (int) config('localguides.sync.stale_after_hours', 24),
            'meta' => [
                'title' => $frame->title.' — '.__('site.name'),
                'description' => __('frames.subtitle'),
                'image' => $frame->assetUrl('thumbnail'),
            ],
        ]);
    }
}
