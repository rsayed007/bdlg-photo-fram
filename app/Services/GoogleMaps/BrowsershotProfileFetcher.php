<?php

namespace App\Services\GoogleMaps;

use App\Contracts\ProfileFetcher;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Spatie\Browsershot\Enums\Polling;

/**
 * Renders the JS-only contributor page with headless Chromium (Puppeteer via
 * Browsershot) and returns text the parser can read.
 *
 * The public page itself only shows level, "X / Y points" and photo/view
 * counts. Clicking the "Local Guide Level N" badge opens the "Contributions
 * from <name>" panel with every stat labelled (Reviews, Photos, Videos,
 * Places added, Roads added…). Photo views only exist as an aria-label
 * ("8,591,611 views"), so numeric aria-labels are appended to the text.
 * See docs/PARSER.md.
 */
class BrowsershotProfileFetcher implements ProfileFetcher
{
    /** Ready when the profile (or its restricted/consent variant) has rendered. */
    private const READY_FUNCTION = <<<'JS'
        document.body && /Local Guide|points|Restricted|Before you continue|unusual traffic/i.test(document.body.innerText)
    JS;

    private const EXTRACT_SCRIPT = <<<'JS'
        (async () => {
            const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
            const title = document.title || '';
            const avatar = (() => {
                const img = Array.from(document.images).find((i) => /googleusercontent\.com\/a(-\/|\/)/.test(i.src) || (/googleusercontent\.com\/.*=s\d+/.test(i.src) && i.naturalWidth >= 48));
                return img ? img.src : null;
            })();

            // Open the "Contributions from …" stats panel.
            const badge = Array.from(document.querySelectorAll('button, [role="button"], span'))
                .find((e) => /Local Guide\s*·?\s*Level\s*\d+/i.test(e.innerText || '') && (e.innerText || '').length < 40);
            let panel = false;
            if (badge) {
                badge.click();
                for (let i = 0; i < 16 && !panel; i++) {
                    await sleep(500);
                    panel = /Contributions from/i.test(document.body.innerText);
                }
            }

            const labels = Array.from(document.querySelectorAll('[aria-label]'))
                .map((e) => e.getAttribute('aria-label') || '')
                .filter((a) => /^[\d.,]+\s+(views?|photos?|reviews?|videos?)$/i.test(a.trim()));

            return JSON.stringify({
                title,
                avatar,
                panel,
                text: (document.body ? document.body.innerText : '') + '\n' + [...new Set(labels)].join('\n'),
            });
        })()
    JS;

    public function fetch(string $contribId): FetchedPage
    {
        $url = str_replace('{id}', rawurlencode($contribId), (string) config('localguides.sync.url'));

        $raw = $this->browser($url)->evaluate(self::EXTRACT_SCRIPT);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new RuntimeException('Unexpected evaluate() result from Chromium');
        }

        return new FetchedPage(
            title: (string) ($data['title'] ?? ''),
            text: (string) ($data['text'] ?? ''),
            avatarUrl: is_string($data['avatar'] ?? null) ? $data['avatar'] : null,
        );
    }

    protected function browser(string $url): Browsershot
    {
        $config = config('localguides.sync');
        $timeoutMs = (int) $config['timeout'] * 1000;

        return Browsershot::url($url)
            ->setChromePath($config['chrome_path'])
            ->setNodeModulePath($config['node_modules'])
            ->userAgent($config['user_agent'])
            ->setExtraHttpHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->windowSize(1366, 900)
            // Maps never goes network-idle (tiles keep streaming), so wait for
            // the profile text instead, then the configurable settle delay.
            ->waitForFunction(self::READY_FUNCTION, Polling::Mutation, $timeoutMs)
            ->setDelay((int) $config['wait_ms'])
            ->timeout((int) $config['timeout'] + 15)
            // Pre-accept Google's consent interstitial.
            ->useCookies(['SOCS' => 'CAESEwgDEgk0ODE3Nzk3MjQaAmVuIAEaBgiA_LyaBg', 'CONSENT' => 'YES+'], '.google.com')
            // Container-only flags; Chromium runs as non-root inside Docker.
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'lang=en-US',
            ]);
    }
}
