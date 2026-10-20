<?php

namespace App\Support;

use App\Models\LocalGuide;

/**
 * Rank position of a guide per metric among listed (active) profiles.
 */
class Rankings
{
    public const METRICS = ['points', 'reviews', 'photos', 'photo_views'];

    /** @return array<string, array{rank: int|null, total: int}> */
    public static function for(LocalGuide $guide): array
    {
        return LeaderboardCache::remember('ranks', ['id' => $guide->id], function () use ($guide) {
            $total = LocalGuide::listed()->count();
            $ranks = [];

            foreach (self::METRICS as $metric) {
                $value = $guide->{$metric};
                $ranks[$metric] = [
                    'rank' => $value === null
                        ? null
                        : LocalGuide::listed()->where($metric, '>', $value)->count() + 1,
                    'total' => $total,
                ];
            }

            return $ranks;
        });
    }
}
