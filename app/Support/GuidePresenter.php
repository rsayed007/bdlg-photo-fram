<?php

namespace App\Support;

use App\Models\LocalGuide;

/**
 * Shapes LocalGuide data for the frontend. Unknown stats stay null — the UI
 * renders them as "—", never 0.
 */
class GuidePresenter
{
    /** @return array<string, mixed> */
    public static function summary(LocalGuide $guide): array
    {
        return [
            'id' => $guide->id,
            'contrib_id' => $guide->contrib_id,
            'name' => $guide->name,
            'avatar_url' => $guide->avatar_url,
            'profile_url' => $guide->profile_url,
            'status' => $guide->status->value,
            'level' => $guide->level,
            'points' => $guide->points,
            'reviews' => $guide->reviews,
            'photos' => $guide->photos,
            'videos' => $guide->videos,
            'photo_views' => $guide->photo_views,
            'places_added' => $guide->places_added,
            'roads_added' => $guide->roads_added,
            'views_per_photo' => $guide->viewsPerPhoto(),
            'last_synced_at' => $guide->last_synced_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(LocalGuide $guide): array
    {
        return [
            ...self::summary($guide),
            'last_attempt_at' => $guide->last_attempt_at?->toIso8601String(),
            'created_at' => $guide->created_at->toIso8601String(),
        ];
    }

    /**
     * Full daily history (the page filters 7d/30d/90d/all client-side).
     *
     * @return list<array<string, mixed>>
     */
    public static function history(LocalGuide $guide): array
    {
        return $guide->snapshots()
            ->orderBy('snap_date')
            ->get(['snap_date', 'level', 'points', 'reviews', 'photos', 'videos', 'photo_views'])
            ->map(fn ($s) => [
                'date' => $s->snap_date->toDateString(),
                'points' => $s->points,
                'reviews' => $s->reviews,
                'photos' => $s->photos,
                'photo_views' => $s->photo_views,
            ])
            ->values()
            ->all();
    }
}
