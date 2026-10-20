<?php

use App\Enums\GuideStatus;
use App\Filament\Pages\HomeSettings;
use App\Filament\Resources\FrameGenerations\Pages\ViewFrameGeneration;
use App\Filament\Resources\Frames\Pages\CreateFrame;
use App\Filament\Resources\LocalGuides\Pages\ListLocalGuides;
use App\Filament\Resources\LocalGuides\Pages\ViewLocalGuide;
use App\Jobs\SyncLocalGuide;
use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Models\StatOverride;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\HomeContent;
use Database\Factories\FrameFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders every admin page', function (string $url) {
    $guide = LocalGuide::factory()->create();
    SyncLog::create(['local_guide_id' => $guide->id, 'status' => 'success', 'duration_ms' => 10, 'raw_excerpt' => 'text']);
    $frame = Frame::factory()->create();
    FrameGeneration::factory()->create(['frame_id' => $frame->id]);

    $url = strtr($url, [
        // LocalGuide and Frame route by contrib_id / slug.
        '{guide}' => $guide->contrib_id,
        '{frame}' => $frame->slug,
        '{log}' => SyncLog::first()->id,
        '{gen}' => FrameGeneration::first()->id,
    ]);

    $this->get($url)->assertOk();
})->with([
    '/admin',
    '/admin/local-guides',
    '/admin/local-guides/{guide}',
    '/admin/events',
    '/admin/events/create',
    '/admin/frames',
    '/admin/frames/create',
    '/admin/frames/{frame}/edit',
    '/admin/frame-generations',
    '/admin/frame-generations/{gen}',
    '/admin/sync-logs',
    '/admin/sync-logs/{log}',
    '/admin/home-settings',
]);

it('blocks members from every admin page', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/admin/local-guides')->assertForbidden();
    $this->get('/admin/home-settings')->assertForbidden();
});

it('queues a priority sync from "Sync now"', function () {
    Queue::fake();
    $guide = LocalGuide::factory()->create();

    Livewire::test(ViewLocalGuide::class, ['record' => $guide->contrib_id])->callAction('syncNow');

    Queue::assertPushedOn('sync-priority', SyncLocalGuide::class);
});

it('overrides stats with an audit note', function () {
    $guide = LocalGuide::factory()->create(['points' => 100, 'reviews' => 5]);

    Livewire::test(ViewLocalGuide::class, ['record' => $guide->contrib_id])
        ->callAction('overrideStats', data: [
            'name' => $guide->name,
            'level' => $guide->level,
            'points' => 150,
            'reviews' => '',
            'photos' => $guide->photos,
            'videos' => $guide->videos,
            'photo_views' => $guide->photo_views,
            'places_added' => $guide->places_added,
            'roads_added' => $guide->roads_added,
            'note' => 'Member sent a screenshot',
        ])
        ->assertHasNoActionErrors();

    expect($guide->fresh()->points)->toBe(150)
        ->and($guide->fresh()->reviews)->toBeNull();

    $audit = StatOverride::sole();
    expect($audit->note)->toBe('Member sent a screenshot')
        ->and($audit->user_id)->toBe($this->admin->id)
        ->and($audit->changes)->toEqual(['points' => ['from' => 100, 'to' => 150], 'reviews' => ['from' => 5, 'to' => null]]);
});

it('requires an audit note for overrides', function () {
    $guide = LocalGuide::factory()->create();

    Livewire::test(ViewLocalGuide::class, ['record' => $guide->contrib_id])
        ->callAction('overrideStats', data: ['note' => ''])
        ->assertHasActionErrors(['note' => 'required']);
});

it('marks a guide inactive and bulk-syncs others', function () {
    Queue::fake();
    $guides = LocalGuide::factory()->count(3)->create();

    Livewire::test(ListLocalGuides::class)
        ->callAction(TestAction::make('toggleActive')->table($guides[0]));
    expect($guides[0]->fresh()->status)->toBe(GuideStatus::Inactive);

    Livewire::test(ListLocalGuides::class)
        ->selectTableRecords($guides->slice(1)->modelKeys())
        ->callAction(TestAction::make('bulkSync')->table()->bulk());
    Queue::assertPushed(SyncLocalGuide::class, 2);
});

it('validates the frame config JSON on create', function () {
    $bad = FrameFactory::sampleConfig();
    $bad['text_slots'][0]['color'] = 'blue';

    Livewire::test(CreateFrame::class)
        ->fillForm([
            'title' => 'Bad frame',
            'slug' => 'bad-frame',
            'size_w' => 1080,
            'size_h' => 1920,
            'config' => json_encode($bad),
        ])
        ->call('create')
        ->assertHasFormErrors(['config']);

    expect(Frame::count())->toBe(0);
});

it('saves the home page content', function () {
    $content = HomeContent::defaults();
    $content['title']['en'] = 'BD Local Guides Community';
    $content['links'] = [['label' => 'Facebook', 'url' => 'https://facebook.com/groups/bdlg']];

    Livewire::test(HomeSettings::class)
        ->fillForm($content)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(HomeContent::forLocale('en')['title'])->toBe('BD Local Guides Community')
        ->and(HomeContent::forLocale('en')['links'][0]['url'])->toBe('https://facebook.com/groups/bdlg');
});

it('features a generation on the home page', function () {
    $generation = FrameGeneration::factory()->create();

    Livewire::test(ViewFrameGeneration::class, ['record' => $generation->id])->callAction('toggleFeatured');

    expect($generation->fresh()->is_featured)->toBeTrue();
});
