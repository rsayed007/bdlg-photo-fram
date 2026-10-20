<?php

namespace App\Services\Frames;

use App\Models\Frame;
use App\Models\LocalGuide;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * Optional server-side renderer (FEATURE_SERVER_RENDER): loads the internal
 * render page — which uses the exact same Konva component as the member's
 * editor — in headless Chromium and returns the PNG. Used for bulk/admin
 * generation and regenerating OG images.
 */
class FrameRenderer
{
    /** Sample stats for previews (Bangla name exercises conjunct shaping). */
    public const SAMPLE_STATS = [
        'name' => 'রোমান সৈয়দ (Roman Syed)',
        'level' => 8,
        'points' => 20232,
        'reviews' => 282,
        'photos' => 1473,
        'videos' => 140,
        'photo_views' => 8591611,
        'places_added' => 53,
        'roads_added' => 0,
        'views_per_photo' => 5832.7,
        'profile_url' => 'https://www.google.com/maps/contrib/107121503923715912205',
    ];

    public static function enabled(): bool
    {
        return (bool) config('localguides.render.enabled');
    }

    /**
     * Signed, expiring URL of the render page.
     *
     * @param  list<string|null>  $photos
     */
    public static function pageUrl(Frame $frame, ?LocalGuide $guide = null, array $photos = [], bool $absolute = true): string
    {
        $params = ['frame' => $frame, 'guide' => $guide?->contrib_id, 'photos' => array_values(array_filter($photos)) ?: null];

        // Signed in relative form (route uses `signed:relative`) so the same URL
        // validates whether it is opened via APP_URL or the internal Docker host.
        $relative = URL::temporarySignedRoute('frames.render', now()->addMinutes(30), array_filter($params), false);

        return $absolute ? url($relative) : $relative;
    }

    /**
     * @param  list<string|null>  $photos
     */
    public function render(Frame $frame, ?LocalGuide $guide = null, array $photos = []): string
    {
        if (! self::enabled()) {
            throw new RuntimeException('Server-side rendering is disabled (FEATURE_SERVER_RENDER=false).');
        }

        // Chromium runs inside the Docker network, so it reaches the app via
        // the internal base URL rather than the public APP_URL.
        $url = rtrim((string) config('localguides.render.internal_url'), '/').self::pageUrl($frame, $guide, $photos, false);

        $config = config('localguides.sync');
        $browser = Browsershot::url($url)
            ->setChromePath($config['chrome_path'])
            ->setNodeModulePath($config['node_modules'])
            ->windowSize($frame->size_w, $frame->size_h)
            ->waitForFunction('document.body && document.body.dataset.rendered === "1"', null, 60000)
            ->timeout(90)
            ->noSandbox()
            ->addChromiumArguments(array_filter([
                'disable-dev-shm-usage',
                ($rules = config('localguides.render.host_rules')) ? "host-resolver-rules={$rules}" : null,
            ]));

        $dataUrl = $browser->evaluate('window.__frameRender');

        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new RuntimeException('Renderer did not return a PNG.');
        }

        return (string) base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
    }
}
