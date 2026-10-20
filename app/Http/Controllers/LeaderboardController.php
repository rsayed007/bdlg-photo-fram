<?php

namespace App\Http\Controllers;

use App\Models\LocalGuide;
use App\Support\GuidePresenter;
use App\Support\LeaderboardCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public const PER_PAGE = 50;

    /** Whitelisted sort keys → SQL expression (unknown values always sort last). */
    public const SORTS = [
        'level' => 'level',
        'points' => 'points',
        'reviews' => 'reviews',
        'photos' => 'photos',
        'videos' => 'videos',
        'photo_views' => 'photo_views',
        'views_per_photo' => 'photo_views / NULLIF(photos, 0)',
        'places_added' => 'places_added',
        'roads_added' => 'roads_added',
        'last_synced_at' => 'last_synced_at',
    ];

    public function __invoke(Request $request): Response
    {
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'points';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $level = $request->integer('level') ?: null;
        $page = max(1, $request->integer('page', 1));

        $params = compact('sort', 'dir', 'search', 'level', 'page');

        $guides = LeaderboardCache::remember('page', $params, function () use ($sort, $dir, $search, $level, $page) {
            $expr = self::SORTS[$sort];

            $paginator = LocalGuide::listed()
                ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->when($level !== null, fn (Builder $q) => $q->where('level', $level))
                ->orderByRaw("({$expr}) IS NULL")
                ->orderByRaw("{$expr} {$dir}")
                ->orderBy('id')
                ->paginate(self::PER_PAGE, ['*'], 'page', $page);

            $offset = ($paginator->currentPage() - 1) * $paginator->perPage();

            return [
                'data' => collect($paginator->items())
                    ->values()
                    ->map(fn (LocalGuide $g, int $i) => [...GuidePresenter::summary($g), 'rank' => $offset + $i + 1])
                    ->all(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
        });

        $levels = LeaderboardCache::remember('levels', [], fn () => LocalGuide::listed()
            ->whereNotNull('level')->distinct()->orderBy('level')->pluck('level')->all());

        return Inertia::render('leaderboard', [
            'guides' => $guides,
            'filters' => ['sort' => $sort, 'dir' => $dir, 'q' => $search, 'level' => $level],
            'levels' => $levels,
            'meta' => [
                'title' => __('leaderboard.title').' — '.__('site.name'),
                'description' => __('leaderboard.subtitle'),
            ],
        ]);
    }
}
