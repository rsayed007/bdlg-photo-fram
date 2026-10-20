<?php

use App\Enums\GuideStatus;
use App\Jobs\SyncLocalGuide;
use App\Models\LocalGuide;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

const ADD_ID = '107121503923715912205';

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    RateLimiter::clear('profile-add');
});

it('adds a profile from a direct URL and queues a priority sync', function () {
    $this->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID.'/contribute/'])
        ->assertRedirect('/guides/'.ADD_ID)
        ->assertSessionHas('queued.created', true);

    $guide = LocalGuide::where('contrib_id', ADD_ID)->sole();
    expect($guide->status)->toBe(GuideStatus::Pending)
        ->and($guide->profile_url)->toBe('https://www.google.com/maps/contrib/'.ADD_ID)
        ->and($guide->points)->toBeNull();

    Queue::assertPushedOn(SyncLocalGuide::QUEUE_PRIORITY, SyncLocalGuide::class);
});

it('adds a profile from the app share text with a level hint', function () {
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/contrib/'.ADD_ID])]);

    $this->post('/guides', [
        'profile_url' => "See what I've posted to Google Maps\nLevel 8 Local Guide\nhttps://maps.app.goo.gl/4KNXhprYBX5dsAdWA",
    ])->assertRedirect('/guides/'.ADD_ID);

    expect(LocalGuide::where('contrib_id', ADD_ID)->value('level'))->toBe(8);
});

it('does not duplicate an existing fresh profile and does not requeue it', function () {
    LocalGuide::factory()->create(['contrib_id' => ADD_ID, 'last_synced_at' => now()->subHour()]);

    $this->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID])
        ->assertSessionHas('queued.created', false);

    expect(LocalGuide::count())->toBe(1);
    Queue::assertNothingPushed();
});

it('requeues an existing stale profile', function () {
    LocalGuide::factory()->create(['contrib_id' => ADD_ID, 'last_synced_at' => now()->subDays(2)]);

    $this->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID]);

    Queue::assertPushed(SyncLocalGuide::class, 1);
});

it('lets a signed-in member claim an unclaimed profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID]);

    expect(LocalGuide::where('contrib_id', ADD_ID)->value('user_id'))->toBe($user->id);
});

it('shows a friendly validation error for bad input', function (string $input) {
    $this->from('/')->post('/guides', ['profile_url' => $input])
        ->assertRedirect('/')
        ->assertSessionHasErrors('profile_url');

    expect(LocalGuide::count())->toBe(0);
})->with([
    'not a url' => 'hello',
    'evil host' => 'https://evil.example.com/maps/contrib/'.ADD_ID,
    'http' => 'http://www.google.com/maps/contrib/'.ADD_ID,
]);

it('rate limits profile additions to 10 per hour per IP', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/1000000000000000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    }

    $this->post('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID])->assertStatus(429);
});

it('returns JSON for API-style requests', function () {
    $this->postJson('/guides', ['profile_url' => 'https://www.google.com/maps/contrib/'.ADD_ID])
        ->assertCreated()
        ->assertJsonPath('guide.contrib_id', ADD_ID)
        ->assertJsonPath('guide.status', 'pending')
        ->assertJsonPath('guide.points', null);
});
