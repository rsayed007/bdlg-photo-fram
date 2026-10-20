<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

/**
 * Politeness gate for Google Maps: waits (instead of releasing the job) until
 * the named "google-maps" limiter has capacity, then adds random jitter.
 *
 * Laravel's RateLimited middleware releases the job back onto the queue, which
 * would burn one of SyncLocalGuide's 3 tries per release. With only 1–2
 * browser workers, blocking here is cheap and keeps tries for real failures.
 */
class GoogleMapsThrottle
{
    public function __construct(private readonly bool $jitter = true) {}

    public function handle(object $job, Closure $next): mixed
    {
        $limiter = app(RateLimiter::class);
        $limit = $this->limit();
        $key = 'google-maps:'.$limit->key;

        while ($limiter->tooManyAttempts($key, $limit->maxAttempts)) {
            sleep(max(1, min(60, $limiter->availableIn($key))));
        }
        $limiter->hit($key, $limit->decaySeconds);

        if ($this->jitter) {
            [$min, $max] = config('localguides.sync.jitter', [5, 15]);
            if ($max > 0) {
                sleep(random_int((int) $min, (int) $max));
            }
        }

        return $next($job);
    }

    private function limit(): Limit
    {
        $callback = app(RateLimiter::class)->limiter('google-maps');
        $limit = $callback ? $callback() : null;

        return $limit instanceof Limit ? $limit : Limit::perMinute(4);
    }
}
