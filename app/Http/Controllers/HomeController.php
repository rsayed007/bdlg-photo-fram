<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Support\FramePresenter;
use App\Support\GuidePresenter;
use App\Support\HomeContent;
use App\Support\LeaderboardCache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Community home page: hero, open event frames (public editor links),
 * admin-featured frames (public share links), top guides, add-profile form.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $content = HomeContent::forLocale(app()->getLocale());

        $frames = Frame::active()->with('event')->get()->map(fn (Frame $f) => FramePresenter::card($f));

        $featured = FrameGeneration::query()
            ->where('is_featured', true)
            ->with(['frame:id,title,slug', 'localGuide:id,name'])
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(fn (FrameGeneration $g) => FramePresenter::generationCard($g));

        $top = LeaderboardCache::remember('home-top', [], fn () => LocalGuide::listed()
            ->orderByDesc('points')
            ->limit(5)
            ->get()
            ->map(fn (LocalGuide $g, int $i) => [...GuidePresenter::summary($g), 'rank' => $i + 1])
            ->all());

        $totals = LeaderboardCache::remember('home-totals', [], function () {
            $row = LocalGuide::listed()
                ->select([
                    DB::raw('COUNT(*) as members'),
                    DB::raw('SUM(reviews) as reviews'),
                    DB::raw('SUM(photos) as photos'),
                    DB::raw('SUM(photo_views) as photo_views'),
                ])
                ->first();

            return [
                'members' => (int) ($row->members ?? 0),
                'reviews' => $row?->reviews !== null ? (int) $row->reviews : null,
                'photos' => $row?->photos !== null ? (int) $row->photos : null,
                'photo_views' => $row?->photo_views !== null ? (int) $row->photo_views : null,
            ];
        });

        return Inertia::render('home', [
            'content' => $content,
            'frames' => $frames,
            'featured' => $featured,
            'top' => $top,
            'totals' => $totals,
            'meta' => [
                'title' => $content['title'],
                'description' => $content['tagline'],
                'url' => route('home'),
            ],
        ]);
    }
}
