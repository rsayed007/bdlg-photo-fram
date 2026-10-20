<?php

namespace App\Console\Commands;

use App\Enums\GuideStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Support\LeaderboardCache;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncAllGuides extends Command
{
    protected $signature = 'guides:sync-all';

    protected $description = 'Queue a sync for every active, pending and restricted Local Guide (daily batch)';

    public function handle(): int
    {
        $jobs = LocalGuide::query()
            ->whereIn('status', GuideStatus::syncable())
            ->orderBy('last_attempt_at')
            ->get()
            ->map(fn (LocalGuide $guide) => new SyncLocalGuide($guide))
            ->all();

        if ($jobs === []) {
            $this->info('Nothing to sync.');

            return self::SUCCESS;
        }

        $batch = Bus::batch($jobs)
            ->name('daily-guide-sync '.now('Asia/Dhaka')->toDateString())
            ->onQueue(SyncLocalGuide::QUEUE_BATCH)
            ->allowFailures()
            // Leaderboard caches are rebuilt once the whole batch has finished.
            ->finally(fn (Batch $batch) => LeaderboardCache::bust())
            ->dispatch();

        $this->info(sprintf('Queued %d profiles (batch %s).', count($jobs), $batch->id));

        return self::SUCCESS;
    }
}
