<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Frame;
use Database\Factories\FrameFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

/**
 * Seeds the "12th Anniversary — 03 Reviews, Photos & Accessibility" sample
 * frame. Placeholder artwork is rendered from Blade/HTML with Chromium (real
 * Bengali shaping) and uploaded to storage; the thumbnail is a composite.
 */
class SampleFrameSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::updateOrCreate(['slug' => '12th-anniversary-mega-meetup'], [
            'name' => '12th Anniversary Mega Meet-up',
            'description' => 'বাংলাদেশ লোকাল গাইডস কমিউনিটির ১২তম বার্ষিকী মেগা মিট-আপ। Bangladesh Local Guides 12th Anniversary Mega Meet-up, Dhaka.',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $config = FrameFactory::sampleConfig();
        $w = 1080;
        $h = 1920;
        $slug = '12th-anniversary-03-reviews-photos-accessibility';
        $dir = "public/frames/{$slug}";

        $background = $this->render('frames.sample-background', $w, $h, $config['photo_slots']);
        $overlay = $this->render('frames.sample-overlay', $w, $h, $config['photo_slots'], transparent: true);

        $thumb = new \Imagick;
        $thumb->readImageBlob($background);
        $layer = new \Imagick;
        $layer->readImageBlob($overlay);
        $thumb->compositeImage($layer, \Imagick::COMPOSITE_OVER, 0, 0);
        $thumb->thumbnailImage(360, 640);
        $thumb->setImageFormat('jpeg');
        $thumb->setImageCompressionQuality(85);

        $opts = ['visibility' => 'public'];
        Storage::put("{$dir}/background.png", $background, $opts + ['ContentType' => 'image/png']);
        Storage::put("{$dir}/overlay.png", $overlay, $opts + ['ContentType' => 'image/png']);
        Storage::put("{$dir}/thumbnail.jpg", $thumb->getImageBlob(), $opts + ['ContentType' => 'image/jpeg']);

        Frame::updateOrCreate(['slug' => $slug], [
            'event_id' => $event->id,
            'title' => '03 — Reviews, Photos & Accessibility',
            'size_w' => $w,
            'size_h' => $h,
            'background_path' => "{$dir}/background.png",
            'overlay_path' => "{$dir}/overlay.png",
            'thumbnail_path' => "{$dir}/thumbnail.jpg",
            'config' => $config,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /** @param list<array<string, mixed>> $slots */
    private function render(string $view, int $w, int $h, array $slots, bool $transparent = false): string
    {
        $shot = Browsershot::html(view($view, compact('w', 'h', 'slots'))->render())
            ->setChromePath(config('localguides.sync.chrome_path'))
            ->setNodeModulePath(config('localguides.sync.node_modules'))
            ->windowSize($w, $h)
            ->noSandbox()
            ->addChromiumArguments(['disable-dev-shm-usage'])
            ->setScreenshotType('png');

        if ($transparent) {
            $shot->hideBackground();
        }

        return $shot->screenshot();
    }
}
