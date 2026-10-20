<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Leaderboard pages are cached per (sort, filters, page) for 10 minutes. All
 * keys embed a version number, so bumping the version invalidates every
 * combination at once without needing cache tags.
 */
class LeaderboardCache
{
    private const VERSION_KEY = 'leaderboard:version';

    public const TTL_SECONDS = 600;

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bust(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);
        }
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $params
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $scope, array $params, Closure $callback): mixed
    {
        ksort($params);
        $key = sprintf('leaderboard:v%d:%s:%s', self::version(), $scope, md5((string) json_encode($params)));

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }
}
