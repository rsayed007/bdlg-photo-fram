<?php

use App\Models\Frame;
use App\Models\FrameGeneration;
use App\Models\LocalGuide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake();
    $this->user = User::factory()->create();
    $this->guide = LocalGuide::factory()->create(['user_id' => $this->user->id, 'name' => 'Roman Syed']);
    $this->frame = Frame::factory()->create(['slug' => 'anniv-03']);
    Storage::put($this->frame->background_path, (string) file_get_contents(base_path('tests/Fixtures/images/gps-rotated.jpg')));
});

function fixtureUpload(string $name, ?string $clientName = null): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'up');
    copy(base_path("tests/Fixtures/images/{$name}"), $tmp);

    return new UploadedFile($tmp, $clientName ?? $name, null, null, true);
}

function pngOfSize(int $w, int $h): UploadedFile
{
    $im = new Imagick;
    $im->newImage($w, $h, 'white');
    $im->setImageFormat('png');
    $tmp = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($tmp, $im->getImageBlob());

    return new UploadedFile($tmp, 'frame.png', 'image/png', null, true);
}

describe('editor page', function () {
    it('requires login', function () {
        $this->get('/frames/anniv-03/create')->assertRedirect('/login');
    });

    it('passes the frame config and the member\'s own profiles', function () {
        LocalGuide::factory()->create(); // someone else's

        $this->actingAs($this->user)->get('/frames/anniv-03/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('frames/create')
            ->where('frame.slug', 'anniv-03')
            ->has('frame.config.photo_slots', 3)
            ->has('guides', 1)
            ->where('guides.0.name', 'Roman Syed'));
    });

    it('hides inactive frames from members', function () {
        $this->frame->update(['is_active' => false]);
        $this->actingAs($this->user)->get('/frames/anniv-03/create')->assertNotFound();
    });

    it('serves frame artwork same-origin with long caching', function () {
        $this->get($this->frame->assetUrl('background'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    });
});

describe('uploads', function () {
    it('re-encodes a JPEG, auto-orients it and strips EXIF incl. GPS', function () {
        $res = $this->actingAs($this->user)->post('/uploads', ['photo' => fixtureUpload('gps-rotated.jpg')])
            ->assertCreated()
            ->assertJsonStructure(['path', 'url', 'width', 'height']);

        // Orientation 6 → the 800×600 original becomes portrait.
        expect($res->json('width'))->toBe(600)->and($res->json('height'))->toBe(800);

        $path = $res->json('path');
        expect($path)->toStartWith("tmp/{$this->user->id}/");
        $stored = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($stored, Storage::get($path));
        $exif = @exif_read_data($stored) ?: [];
        expect($exif)->not->toHaveKey('GPSLatitude')->and($exif['Orientation'] ?? 1)->toBe(1);
    });

    it('converts iPhone HEIC to JPEG', function () {
        $res = $this->actingAs($this->user)->post('/uploads', ['photo' => fixtureUpload('sample.heic')])->assertCreated();

        $stored = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($stored, Storage::get($res->json('path')));
        expect((new finfo(FILEINFO_MIME_TYPE))->file($stored))->toBe('image/jpeg');
    });

    it('rejects files by real MIME type, not extension', function () {
        $this->actingAs($this->user)->postJson('/uploads', ['photo' => fixtureUpload('fake.jpg', 'holiday.jpg')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    });

    it('rejects files over 10 MB', function () {
        $big = UploadedFile::fake()->create('big.jpg', 10241, 'image/jpeg');
        $this->actingAs($this->user)->postJson('/uploads', ['photo' => $big])->assertJsonValidationErrors('photo');
    });

    it('requires login and serves temp files only via signed URLs', function () {
        $this->postJson('/uploads', ['photo' => fixtureUpload('gps-rotated.jpg')])->assertUnauthorized();

        $res = $this->actingAs($this->user)->post('/uploads', ['photo' => fixtureUpload('gps-rotated.jpg')]);
        $this->get($res->json('url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/uploads/'.$res->json('path'))->assertForbidden();
    });

    it('rate limits uploads to 30 per hour', function () {
        foreach (range(1, 30) as $i) {
            $this->actingAs($this->user)->postJson('/uploads', ['photo' => fixtureUpload('fake.jpg')]);
        }
        $this->actingAs($this->user)->postJson('/uploads', ['photo' => fixtureUpload('fake.jpg')])->assertStatus(429);
    });
});

describe('generations', function () {
    it('stores the PNG, moves source photos and snapshots the stats', function () {
        Storage::put("tmp/{$this->user->id}/a.jpg", 'x');

        $res = $this->actingAs($this->user)->post('/frames/anniv-03/generations', [
            'image' => pngOfSize(1080, 1920),
            'local_guide_id' => $this->guide->id,
            'sources' => ["tmp/{$this->user->id}/a.jpg", null, null],
        ], ['Accept' => 'application/json'])->assertCreated();

        $generation = FrameGeneration::sole();
        expect($res->json('share_url'))->toBe(route('share.show', $generation))
            ->and($generation->stats_snapshot['name'])->toBe('Roman Syed')
            ->and($generation->stats_snapshot['points'])->toBe($this->guide->points)
            ->and($generation->source_photo_paths)->toBe(["originals/{$generation->id}/0.jpg"])
            ->and($generation->user_id)->toBe($this->user->id);

        Storage::assertExists($generation->output_path);
        Storage::assertExists("originals/{$generation->id}/0.jpg");
        Storage::assertMissing("tmp/{$this->user->id}/a.jpg");
    });

    it('rejects images that are not a PNG at the native frame size', function () {
        $this->actingAs($this->user)->postJson('/frames/anniv-03/generations', [
            'image' => pngOfSize(540, 960),
            'local_guide_id' => $this->guide->id,
        ])->assertJsonValidationErrors('image');
    });

    it('only allows the member\'s own profile and own temp uploads', function () {
        $other = LocalGuide::factory()->create();
        $this->actingAs($this->user)->postJson('/frames/anniv-03/generations', [
            'image' => pngOfSize(1080, 1920),
            'local_guide_id' => $other->id,
        ])->assertJsonValidationErrors('local_guide_id');

        Storage::put('tmp/999/x.jpg', 'x');
        $this->actingAs($this->user)->postJson('/frames/anniv-03/generations', [
            'image' => pngOfSize(1080, 1920),
            'local_guide_id' => $this->guide->id,
            'sources' => ['tmp/999/x.jpg'],
        ])->assertJsonValidationErrors('sources');
    });

    it('lets only the owner (or an admin) delete a generation, removing its files', function () {
        $generation = FrameGeneration::factory()->create(['user_id' => $this->user->id, 'frame_id' => $this->frame->id]);
        Storage::put($generation->output_path, 'png');

        $this->actingAs(User::factory()->create())->delete("/f/{$generation->id}")->assertForbidden();
        $this->actingAs($this->user)->delete("/f/{$generation->id}")->assertRedirect('/dashboard');

        expect(FrameGeneration::count())->toBe(0);
        Storage::assertMissing($generation->output_path);
    });
});

describe('share page', function () {
    it('is public and renders OG / Twitter tags in the server HTML', function () {
        $generation = FrameGeneration::factory()->create(['frame_id' => $this->frame->id, 'local_guide_id' => $this->guide->id]);

        $html = $this->get("/f/{$generation->id}")->assertOk()->getContent();

        $image = $generation->imageUrl();
        expect($image)->toStartWith('http')
            ->and($html)->toContain('<meta property="og:image" content="'.e($image).'">')
            ->and($html)->toContain('<meta property="og:title" content="Roman Syed')
            ->and($html)->toContain('<meta property="og:description"')
            ->and($html)->toContain('<meta name="twitter:card" content="summary_large_image">')
            ->and($html)->toContain('<meta property="og:image:width" content="1080">');
    });

    it('counts downloads and serves the PNG as an attachment', function () {
        $generation = FrameGeneration::factory()->create([
            'frame_id' => $this->frame->id,
            'local_guide_id' => $this->guide->id,
            'stats_snapshot' => ['name' => 'Roman Syed'],
        ]);
        Storage::put($generation->output_path, 'png-bytes');

        $this->get("/f/{$generation->id}/download")
            ->assertOk()
            ->assertDownload('roman-syed-anniv-03.png');

        expect($generation->fresh()->downloads)->toBe(1);
    });

    it('404s for unknown ULIDs', function () {
        $this->get('/f/01JABCDEFGHJKMNPQRSTVWXYZ0')->assertNotFound();
    });
});

describe('render page & housekeeping', function () {
    it('requires a valid signature for the internal render page', function () {
        $this->get("/internal/frames/{$this->frame->slug}/render")->assertForbidden();

        $url = URL::temporarySignedRoute('frames.render', now()->addMinutes(5), ['frame' => $this->frame], false);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('frames/render')->where('stats.level', 8));
    });

    it('purges original uploads after 30 days but keeps the final image', function () {
        $old = FrameGeneration::factory()->create(['frame_id' => $this->frame->id, 'source_photo_paths' => ['originals/x/0.jpg']]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $recent = FrameGeneration::factory()->create(['frame_id' => $this->frame->id, 'source_photo_paths' => ['originals/y/0.jpg']]);
        Storage::put('originals/x/0.jpg', 'a');
        Storage::put('originals/y/0.jpg', 'b');
        Storage::put($old->output_path, 'final');

        $this->artisan('frames:purge-sources')->assertSuccessful();

        Storage::assertMissing('originals/x/0.jpg');
        Storage::assertExists('originals/y/0.jpg');
        Storage::assertExists($old->output_path);
        expect($old->fresh()->sources_purged_at)->not->toBeNull()
            ->and($recent->fresh()->sources_purged_at)->toBeNull();
    });
});
