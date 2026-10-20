<?php

namespace App\Http\Controllers;

use App\Enums\GuideStatus;
use App\Models\LocalGuide;
use App\Support\GuidePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public const MAX = 3;

    public function __invoke(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => preg_match('/^\d{5,32}$/', $id))
            ->unique()
            ->take(self::MAX)
            ->values();

        $guides = LocalGuide::whereIn('contrib_id', $ids)
            ->where('status', '!=', GuideStatus::Inactive)
            ->get()
            ->sortBy(fn (LocalGuide $g) => $ids->search($g->contrib_id))
            ->values();

        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        return Inertia::render('compare', [
            'guides' => $guides->map(fn (LocalGuide $g) => [
                ...GuidePresenter::summary($g),
                'history' => GuidePresenter::history($g),
            ]),
            'q' => $search,
            // Lazy: only evaluated on the partial reload made by the search box.
            'candidates' => Inertia::optional(fn () => $search === '' ? [] : LocalGuide::listed()
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->whereNotIn('contrib_id', $ids)
                ->orderByDesc('points')
                ->limit(8)
                ->get()
                ->map(fn (LocalGuide $g) => GuidePresenter::summary($g))),
            'max' => self::MAX,
            'meta' => [
                'title' => __('compare.title').' — '.__('site.name'),
                'description' => __('compare.subtitle'),
            ],
        ]);
    }
}
