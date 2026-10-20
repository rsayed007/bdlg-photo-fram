<?php

use Spatie\Browsershot\Browsershot;

/*
 * Runs real Chromium inside the container (`make test` runs this group
 * separately; CI without Chromium can skip it with --exclude-group=browser).
 *
 * A Bengali conjunct (e.g. ক্ষ = ক + ্ + ষ) only renders correctly when the
 * font is present AND the text is shaped (HarfBuzz). With shaping, the
 * conjunct is a single narrower glyph; forcing the parts apart with a
 * zero-width non-joiner (ক্‌ষ) makes it visibly wider. If fonts or shaping
 * were missing, both strings would be the same width (or tofu boxes).
 */
function chromium(): Browsershot
{
    return Browsershot::html('<html></html>')
        ->setChromePath(config('localguides.sync.chrome_path'))
        ->setNodeModulePath(config('localguides.sync.node_modules'))
        ->noSandbox()
        ->addChromiumArguments(['disable-dev-shm-usage']);
}

// Both fonts are installed system-wide in the image (see Dockerfile).
it('shapes Bengali conjuncts with the bundled fonts', function (string $family) {
    $html = <<<HTML
        <html><head><style>
            body { margin: 0; font: 64px '{$family}'; }
            span { white-space: nowrap; }
        </style></head>
        <body><span id="c">ক্ষ ন্ত্র স্ক</span><br><span id="s">ক্‌ষ ন্‌ত্‌র স্‌ক</span></body></html>
    HTML;

    $result = json_decode(chromium()->setHtml($html)->evaluate(<<<'JS'
        (async () => {
            await document.fonts.ready;
            const w = (id) => document.getElementById(id).getBoundingClientRect().width;
            return JSON.stringify({ conjunct: w('c'), separated: w('s') });
        })()
    JS), true);

    expect($result['conjunct'])->toBeGreaterThan(0)
        ->and($result['conjunct'])->toBeLessThan($result['separated'] * 0.95);
})->with(['Hind Siliguri', 'Noto Sans Bengali'])->group('browser');

it('renders a Bengali + English screenshot for manual inspection', function () {
    $path = storage_path('screenshots/bengali-render.png');
    @mkdir(dirname($path), 0775, true);

    chromium()->setHtml(<<<'HTML'
        <html><body style="margin:24px;font:700 48px 'Hind Siliguri','Noto Sans Bengali',sans-serif">
            <div>বাংলাদেশ লোকাল গাইডস</div>
            <div>ক্ষ ন্ত্র স্ক জ্ঞ শ্রী</div>
            <div style="font-family:Poppins">Level 8 Local Guide · 20.2K pts</div>
        </body></html>
    HTML)->windowSize(900, 300)->save($path);

    expect(filesize($path))->toBeGreaterThan(5000);
})->group('browser');
