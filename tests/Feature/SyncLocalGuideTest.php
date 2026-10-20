<?php

use App\Contracts\ProfileFetcher;
use App\Enums\GuideStatus;
use App\Enums\SyncStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Models\SyncLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeProfileFetcher;

beforeEach(function () {
    $this->fetcher = new FakeProfileFetcher;
    $this->app->instance(ProfileFetcher::class, $this->fetcher);
    config(['localguides.sync.jitter' => [0, 0]]);
});

function runSync(LocalGuide $guide): void
{
    app()->call([new SyncLocalGuide($guide), 'handle']);
}

it('updates stats, snapshot and log on success', function () {
    $guide = LocalGuide::factory()->pending()->create(['contrib_id' => '107121503923715912205']);
    $this->fetcher->push(FakeProfileFetcher::fixture('107121503923715912205'));

    runSync($guide);

    $guide->refresh();
    expect($guide->status)->toBe(GuideStatus::Active)
        ->and($guide->name)->toBe('Roman Syed')
        ->and($guide->level)->toBe(8)
        ->and($guide->points)->toBe(20232)
        ->and($guide->reviews)->toBe(282)
        ->and($guide->photos)->toBe(1473)
        ->and($guide->videos)->toBe(140)
        ->and($guide->photo_views)->toBe(8591611)
        ->and($guide->places_added)->toBe(53)
        ->and($guide->roads_added)->toBe(0)
        ->and($guide->avatar_url)->not->toBeNull()
        ->and($guide->fail_count)->toBe(0)
        ->and($guide->last_synced_at)->not->toBeNull();

    $snapshot = $guide->snapshots()->sole();
    expect($snapshot->points)->toBe(20232)
        ->and($snapshot->snap_date->toDateString())->toBe(now('Asia/Dhaka')->toDateString());

    $log = SyncLog::sole();
    expect($log->status)->toBe(SyncStatus::Success)
        ->and(mb_strlen((string) $log->raw_excerpt))->toBeLessThanOrEqual(2000);
});

it('upserts a single snapshot per day', function () {
    $guide = LocalGuide::factory()->pending()->create();
    $this->fetcher->push(FakeProfileFetcher::fixture('107121503923715912205'))
        ->push(FakeProfileFetcher::fixture('107121503923715912205'));

    runSync($guide);
    runSync($guide);

    expect($guide->snapshots()->count())->toBe(1);
});

it('does not overwrite known stats with unknown ones', function () {
    $guide = LocalGuide::factory()->create(['reviews' => 280, 'videos' => 139, 'places_added' => 50]);
    $this->fetcher->push(FakeProfileFetcher::fixture('main-page-only'));

    runSync($guide);

    $guide->refresh();
    expect($guide->reviews)->toBe(280)
        ->and($guide->videos)->toBe(139)
        ->and($guide->photos)->toBe(1436)
        ->and($guide->points)->toBe(20232);
});

it('marks restricted profiles and keeps their previous stats', function () {
    $guide = LocalGuide::factory()->create(['points' => 999, 'reviews' => 12]);
    $this->fetcher->push(FakeProfileFetcher::fixture('restricted'));

    runSync($guide);

    $guide->refresh();
    expect($guide->status)->toBe(GuideStatus::Restricted)
        ->and($guide->points)->toBe(999)
        ->and($guide->reviews)->toBe(12);
    expect(SyncLog::sole()->status)->toBe(SyncStatus::Restricted);
    expect($guide->snapshots()->count())->toBe(0);
});

it('counts failures and flips to failed after 3 consecutive ones', function () {
    $guide = LocalGuide::factory()->create(['points' => 500]);

    foreach (range(1, 3) as $attempt) {
        $this->fetcher->push(new RuntimeException('Navigation timeout of 45000 ms exceeded'));
        expect(fn () => runSync($guide))->toThrow(RuntimeException::class);

        $guide->refresh();
        expect($guide->fail_count)->toBe($attempt)
            ->and($guide->status)->toBe($attempt < 3 ? GuideStatus::Active : GuideStatus::Failed)
            ->and($guide->points)->toBe(500);
    }

    expect(SyncLog::where('status', SyncStatus::Failed)->count())->toBe(3)
        ->and(SyncLog::first()->error)->toContain('Navigation timeout');
});

it('resets the failure counter after a success', function () {
    $guide = LocalGuide::factory()->create(['status' => GuideStatus::Failed, 'fail_count' => 3]);
    $this->fetcher->push(FakeProfileFetcher::fixture('107121503923715912205'));

    runSync($guide);

    expect($guide->refresh()->fail_count)->toBe(0)
        ->and($guide->status)->toBe(GuideStatus::Active);
});

it('treats a consent / captcha page as a failure, not as restricted', function () {
    $guide = LocalGuide::factory()->create();
    $this->fetcher->push(FakeProfileFetcher::fixture('consent'));

    expect(fn () => runSync($guide))->toThrow(RuntimeException::class);
    expect($guide->refresh()->status)->toBe(GuideStatus::Active)
        ->and($guide->fail_count)->toBe(1);
});

it('is configured with tries, backoff and queues per spec', function () {
    $guide = LocalGuide::factory()->create();
    $job = new SyncLocalGuide($guide);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900])
        ->and($job->queue)->toBe('sync')
        ->and(SyncLocalGuide::priority($guide)->queue)->toBe('sync-priority');
});

it('daily command batches every active, pending and restricted profile', function () {
    Bus::fake();
    LocalGuide::factory()->count(2)->create();
    LocalGuide::factory()->pending()->create();
    LocalGuide::factory()->restricted()->create();
    LocalGuide::factory()->create(['status' => GuideStatus::Failed]);
    LocalGuide::factory()->create(['status' => GuideStatus::Inactive]);

    $this->artisan('guides:sync-all')->assertSuccessful();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 4 && $batch->queue() === 'sync');
});

it('schedules the daily sync at 23:00 Dhaka time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'guides:sync-all'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 23 * * *')
        ->and($event->timezone)->toBe('Asia/Dhaka');
});

it('runs through the real queue middleware without burning attempts', function () {
    Queue::fake();
    $guide = LocalGuide::factory()->create();

    SyncLocalGuide::dispatch($guide, true);
    SyncLocalGuide::dispatch($guide, true); // unique per guide

    Queue::assertPushed(SyncLocalGuide::class, 1);
});
