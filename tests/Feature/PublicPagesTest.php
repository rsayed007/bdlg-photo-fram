<?php

use App\Enums\GuideStatus;
use App\Models\Event;
use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Models\LocalGuideSnapshot;
use App\Support\LeaderboardCache;
use Inertia\Testing\AssertableInertia as Assert;

describe('home page', function () {
    it('lists active frames with their public editor links', function () {
        $event = Event::factory()->create(['name' => '12th Anniversary Mega Meet-up']);
        $frame = Frame::factory()->create(['event_id' => $event->id, 'title' => '03 Reviews, Photos & Accessibility', 'slug' => 'anniv-12-03']);
        Frame::factory()->create(['is_active' => false, 'slug' => 'hidden-frame']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('frames', 1)
            ->where('frames.0.title', '03 Reviews, Photos & Accessibility')
            ->where('frames.0.event', '12th Anniversary Mega Meet-up')
            ->where('frames.0.create_url', route('frames.create', $frame))
            ->has('content.title')
            ->has('meta.title'));
    });

    it('shows only admin-featured generations, linking to their share pages', function () {
        $featured = FrameGeneration::factory()->featured()->create();
        FrameGeneration::factory()->create(); // not featured

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('featured', 1)
            ->where('featured.0.id', $featured->id)
            ->where('featured.0.share_url', route('share.show', $featured)));
    });

    it('shows the top 5 guides and community totals', function () {
        LocalGuide::factory()->count(7)->create();
        LocalGuide::factory()->restricted()->create(['points' => 99999999]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('top', 5)
            ->where('top.0.rank', 1)
            ->where('totals.members', 7));
    });

    it('renders in Bangla by default and switches to English', function () {
        config(['app.locale' => 'bn']); // phpunit.xml pins `en` for the rest of the suite

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('locale', 'bn')->where('translations', fn ($t) => $t['nav.home'] === 'হোম'));

        $this->post('/locale/en')->assertRedirect();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('translations', fn ($t) => $t['nav.home'] === 'Home'));
    });
});

describe('leaderboard', function () {
    beforeEach(fn () => app()->setLocale('en'));

    it('sorts by points descending by default, unknown values last', function () {
        LocalGuide::factory()->create(['name' => 'Low', 'points' => 10]);
        LocalGuide::factory()->create(['name' => 'High', 'points' => 1000]);
        LocalGuide::factory()->create(['name' => 'Unknown', 'points' => null]);

        $this->get('/leaderboard')->assertInertia(fn (Assert $page) => $page
            ->component('leaderboard')
            ->where('guides.data.0.name', 'High')
            ->where('guides.data.1.name', 'Low')
            ->where('guides.data.2.name', 'Unknown')
            ->where('guides.data.2.points', null)
            ->where('guides.data.0.rank', 1));
    });

    it('sorts by any whitelisted numeric column in both directions', function (string $column) {
        LocalGuide::factory()->create(['name' => 'A', $column => 5]);
        LocalGuide::factory()->create(['name' => 'B', $column => 50]);

        $this->get("/leaderboard?sort={$column}&dir=asc")->assertInertia(fn (Assert $page) => $page
            ->where('guides.data.0.name', 'A')
            ->where('filters.sort', $column)
            ->where('filters.dir', 'asc'));

        $this->get("/leaderboard?sort={$column}&dir=desc")->assertInertia(fn (Assert $page) => $page->where('guides.data.0.name', 'B'));
    })->with(['level', 'points', 'reviews', 'photos', 'videos', 'photo_views', 'places_added', 'roads_added']);

    it('sorts by computed views per photo', function () {
        LocalGuide::factory()->create(['name' => 'Efficient', 'photos' => 10, 'photo_views' => 10000]);
        LocalGuide::factory()->create(['name' => 'Bulk', 'photos' => 1000, 'photo_views' => 20000]);
        LocalGuide::factory()->create(['name' => 'NoPhotos', 'photos' => 0, 'photo_views' => 0]);

        $this->get('/leaderboard?sort=views_per_photo')->assertInertia(fn (Assert $page) => $page
            ->where('guides.data.0.name', 'Efficient')
            ->where('guides.data.0.views_per_photo', 1000)
            ->where('guides.data.2.name', 'NoPhotos')
            ->where('guides.data.2.views_per_photo', null));
    });

    it('ignores unknown sort columns (no SQL injection)', function () {
        LocalGuide::factory()->create();

        $this->get('/leaderboard?sort=id;drop table users&dir=sideways')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'points')->where('filters.dir', 'desc'));
    });

    it('paginates 50 per page', function () {
        LocalGuide::factory()->count(55)->create();

        $this->get('/leaderboard?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('guides.data', 5)
            ->where('guides.current_page', 2)
            ->where('guides.last_page', 2)
            ->where('guides.total', 55)
            ->where('guides.data.0.rank', 51));
    });

    it('searches by name and filters by level', function () {
        LocalGuide::factory()->create(['name' => 'Rahim Uddin', 'level' => 7]);
        LocalGuide::factory()->create(['name' => 'Karim Uddin', 'level' => 9]);
        LocalGuide::factory()->create(['name' => 'Someone Else', 'level' => 7]);

        $this->get('/leaderboard?q=uddin')->assertInertia(fn (Assert $page) => $page->has('guides.data', 2));
        $this->get('/leaderboard?q=uddin&level=7')->assertInertia(fn (Assert $page) => $page
            ->has('guides.data', 1)
            ->where('guides.data.0.name', 'Rahim Uddin'));
    });

    it('only lists active profiles', function () {
        LocalGuide::factory()->create();
        LocalGuide::factory()->pending()->create();
        LocalGuide::factory()->restricted()->create();
        LocalGuide::factory()->create(['status' => GuideStatus::Inactive]);

        $this->get('/leaderboard')->assertInertia(fn (Assert $page) => $page->where('guides.total', 1));
    });

    it('caches pages until the cache is busted', function () {
        config(['cache.default' => 'array']);
        LocalGuide::factory()->create(['name' => 'First']);
        $this->get('/leaderboard')->assertInertia(fn (Assert $page) => $page->where('guides.total', 1));

        LocalGuide::factory()->create(['name' => 'Second']);
        $this->get('/leaderboard')->assertInertia(fn (Assert $page) => $page->where('guides.total', 1));

        LeaderboardCache::bust();
        $this->get('/leaderboard')->assertInertia(fn (Assert $page) => $page->where('guides.total', 2));
    });
});

describe('profile page', function () {
    it('shows stats, ranks and history', function () {
        $guide = LocalGuide::factory()->create(['points' => 500, 'reviews' => null]);
        LocalGuide::factory()->create(['points' => 900]);
        LocalGuideSnapshot::factory()->create(['local_guide_id' => $guide->id, 'snap_date' => now()->subDay()->toDateString()]);
        LocalGuideSnapshot::factory()->create(['local_guide_id' => $guide->id, 'snap_date' => now()->toDateString()]);

        $this->get("/guides/{$guide->contrib_id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('guides/show')
            ->where('guide.points', 500)
            ->where('guide.reviews', null)
            ->where('ranks.points.rank', 2)
            ->where('ranks.points.total', 2)
            ->where('ranks.reviews.rank', null)
            ->has('history', 2));
    });

    it('returns 404 for unknown or inactive profiles', function () {
        $this->get('/guides/123456789012')->assertNotFound();
        $inactive = LocalGuide::factory()->create(['status' => GuideStatus::Inactive]);
        $this->get("/guides/{$inactive->contrib_id}")->assertNotFound();
    });

    it('exposes a JSON status endpoint for polling', function () {
        $guide = LocalGuide::factory()->pending()->create();

        $this->getJson("/guides/{$guide->contrib_id}/status")
            ->assertOk()
            ->assertJsonPath('guide.status', 'pending')
            ->assertJsonPath('stale', true);
    });
});

describe('compare page', function () {
    it('compares up to 3 profiles in the requested order', function () {
        $guides = LocalGuide::factory()->count(4)->create();
        $ids = $guides->pluck('contrib_id');

        $this->get('/compare?ids='.$ids->reverse()->implode(','))->assertInertia(fn (Assert $page) => $page
            ->component('compare')
            ->has('guides', 3)
            ->where('guides.0.contrib_id', $ids[3])
            ->has('guides.0.history'));
    });

    it('ignores malformed ids', function () {
        $this->get('/compare?ids=abc,../etc,1')->assertOk()->assertInertia(fn (Assert $page) => $page->has('guides', 0));
    });
});

it('serves the privacy page', function () {
    $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page->component('privacy'));
});
