<?php

namespace App\Jobs;

use App\Contracts\ProfileFetcher;
use App\Enums\GuideStatus;
use App\Enums\SyncStatus;
use App\Jobs\Middleware\GoogleMapsThrottle;
use App\Models\LocalGuide;
use App\Models\LocalGuideSnapshot;
use App\Models\SyncLog;
use App\Services\GoogleMaps\ProfileTextParser;
use App\Support\LeaderboardCache;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncLocalGuide implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public const QUEUE_PRIORITY = 'sync-priority';

    public const QUEUE_BATCH = 'sync';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** Covers throttle waits + jitter + a 45 s browser timeout. */
    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(public LocalGuide $guide, bool $priority = false)
    {
        $this->onQueue($priority ? self::QUEUE_PRIORITY : self::QUEUE_BATCH);
    }

    public static function priority(LocalGuide $guide): self
    {
        return new self($guide, true);
    }

    public function uniqueId(): string
    {
        return (string) $this->guide->id;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new GoogleMapsThrottle(jitter: (bool) config('localguides.sync.jitter.1'))];
    }

    public function handle(ProfileFetcher $fetcher, ProfileTextParser $parser): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $guide = $this->guide->fresh() ?? $this->guide;
        $started = microtime(true);
        $guide->last_attempt_at = now();

        try {
            $page = $fetcher->fetch($guide->contrib_id);

            if ($parser->isBlockedPage($page->text)) {
                throw new RuntimeException('Google served a consent/captcha page instead of the profile');
            }
        } catch (Throwable $e) {
            $this->recordFailure($guide, $e, $started, $page->text ?? null);

            throw $e;
        }

        $parsed = $parser->parse($page->text, $page->title);
        $excerpt = $this->excerpt($page->text);
        $duration = $this->elapsedMs($started);

        if ($parsed->isRestricted()) {
            // Private profile: keep previously stored stats, only flag it.
            $guide->status = GuideStatus::Restricted;
            $guide->fail_count = 0;
            if ($parsed->name !== null) {
                $guide->name = $parsed->name;
            }
            $guide->save();

            $this->log($guide, SyncStatus::Restricted, $duration, null, $excerpt);

            return;
        }

        $wasListed = $guide->status === GuideStatus::Active;

        $guide->fill($parsed->knownStats());
        $guide->name = $parsed->name ?? $guide->name;
        $guide->avatar_url = $page->avatarUrl ?? $guide->avatar_url;
        $guide->status = GuideStatus::Active;
        $guide->fail_count = 0;
        $guide->last_synced_at = now();
        $guide->save();

        LocalGuideSnapshot::upsert([[
            'local_guide_id' => $guide->id,
            'snap_date' => now('Asia/Dhaka')->toDateString(),
            ...array_map(fn (string $f) => $guide->{$f}, array_combine(LocalGuide::SNAPSHOT_FIELDS, LocalGuide::SNAPSHOT_FIELDS)),
        ]], ['local_guide_id', 'snap_date'], LocalGuide::SNAPSHOT_FIELDS);

        $this->log($guide, SyncStatus::Success, $duration, null, $excerpt);

        // New profiles should show up right away, not after the next daily bust.
        if (! $wasListed && $this->batch() === null) {
            LeaderboardCache::bust();
        }
    }

    private function recordFailure(LocalGuide $guide, Throwable $e, float $started, ?string $text): void
    {
        $guide->fail_count++;
        if ($guide->fail_count >= (int) config('localguides.sync.failures_before_failed', 3)) {
            $guide->status = GuideStatus::Failed;
        }
        $guide->save();

        $this->log($guide, SyncStatus::Failed, $this->elapsedMs($started), Str::limit($e->getMessage(), 1000), $this->excerpt($text));

        Log::warning('Local guide sync failed', [
            'local_guide_id' => $guide->id,
            'contrib_id' => $guide->contrib_id,
            'attempt' => $this->attempts(),
            'error' => Str::limit($e->getMessage(), 300),
        ]);
    }

    private function log(LocalGuide $guide, SyncStatus $status, int $durationMs, ?string $error, ?string $excerpt): void
    {
        SyncLog::create([
            'local_guide_id' => $guide->id,
            'status' => $status,
            'duration_ms' => $durationMs,
            'error' => $error,
            'raw_excerpt' => $excerpt,
        ]);
    }

    /** Never store full page content — only the first N chars of text. */
    private function excerpt(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return mb_substr($text, 0, (int) config('localguides.sync.excerpt_chars', 2000));
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
