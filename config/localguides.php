<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile URL resolution (Module A)
    |--------------------------------------------------------------------------
    | Every host in a redirect chain must be in this allowlist (SSRF protection).
    */
    'allowed_hosts' => [
        'contrib' => ['www.google.com', 'google.com', 'maps.google.com'],
        'short' => ['maps.app.goo.gl'],
        // Hosts that may appear *between* the short link and the final URL.
        'intermediate' => ['goo.gl', 'consent.google.com'],
    ],
    'resolver' => [
        'max_redirects' => 5,
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync engine (Module B)
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'per_minute' => (int) env('LG_SYNC_PER_MINUTE', 4),
        'wait_ms' => (int) env('LG_SYNC_WAIT_MS', 4000),
        'timeout' => (int) env('LG_SYNC_TIMEOUT', 45),
        'jitter' => [(int) env('LG_SYNC_JITTER_MIN', 5), (int) env('LG_SYNC_JITTER_MAX', 15)],
        'failures_before_failed' => 3,
        'stale_after_hours' => 24,
        'user_agent' => env('LG_SYNC_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'),
        'url' => 'https://www.google.com/maps/contrib/{id}/contribute?hl=en&gl=bd',
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium'),
        'node_modules' => env('BROWSERSHOT_NODE_MODULES', '/usr/local/lib/node_modules'),
        'excerpt_chars' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Parser patterns (see docs/PARSER.md)
    |--------------------------------------------------------------------------
    | Applied to document.body.innerText of the rendered profile (hl=en).
    | Each pattern's first capture group is the number. Numbers are stripped of
    | non-digits; a pattern that doesn't match yields null (never 0).
    | Verified against tests/Fixtures/profiles/*.txt.
    */
    'patterns' => [
        'level' => '/Level\s+(\d+)\s+Local\s+Guide/i',
        'points' => '/([\d,.]+)\s+points/i',
        'reviews' => '/([\d,.]+)\s+reviews?\b/i',
        'photos' => '/([\d,.]+)\s+photos?\b/i',
        'videos' => '/([\d,.]+)\s+videos?\b/i',
        'photo_views' => '/([\d,.]+)\s+views\b/i',
        'places_added' => '/([\d,.]+)\s+places?\s+added/i',
        'roads_added' => '/([\d,.]+)\s+roads?\s+added/i',
    ],

    /*
    | Page-title suffixes stripped to get the contributor's name.
    */
    'title_suffixes' => [' - Google Maps', ' – Google Maps'],

    /*
    | Text that indicates Google served something other than the profile
    | (consent wall, captcha). Treated as a failed attempt, not "restricted".
    */
    'blocked_markers' => [
        'Before you continue to Google',
        'unusual traffic from your computer network',
        'Our systems have detected unusual traffic',
    ],
];
