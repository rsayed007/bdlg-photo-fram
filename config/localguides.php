<?php

return [

    /*
    | Admin account created by `make fresh` (AdminSeeder).
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
        'name' => env('ADMIN_NAME', 'BDLG Admin'),
    ],

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
        // Settle delay after the profile text appears (Maps never goes network-idle).
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
    | Optional server-side frame renderer (Browsershot)
    |--------------------------------------------------------------------------
    */
    'render' => [
        'enabled' => (bool) env('FEATURE_SERVER_RENDER', false),
        // How Chromium inside Docker reaches the app (not the public APP_URL).
        'internal_url' => env('RENDER_INTERNAL_URL', 'http://nginx'),
        // Dev only: lets Chromium load Vite dev assets ("MAP localhost vite").
        'host_rules' => env('BROWSERSHOT_HOST_RULES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parser patterns (see docs/PARSER.md)
    |--------------------------------------------------------------------------
    | Applied to the text returned by BrowsershotProfileFetcher: the page's
    | innerText after opening the "Contributions from <name>" panel, plus
    | numeric aria-labels (e.g. "8,591,611 views"). The first non-empty capture
    | group is the number; non-digits are stripped. No match → null (never 0).
    |
    | Real format (verified 2026-10 against tests/Fixtures/profiles/*.txt):
    |   Local Guide Level 8          20,232 / 50,000 points
    |   Reviews\n282                 Photos\n1,473        Places added\n53
    | The inline variants ("Level 8 Local Guide", "282 reviews") are kept as
    | fallbacks for older/mobile layouts.
    */
    'patterns' => [
        'level' => '/Local\s+Guide\s*[·•]?\s*Level\s+(\d{1,2})\b|\bLevel\s+(\d{1,2})\s+Local\s+Guide/i',
        // "20,232 / 50,000 points" → 20,232 (the second number is the next-level target).
        // Inline alternatives use \h (same line only) so a number on the line
        // above a label (e.g. "Ratings\n2,201\n\nPhotos") is never picked up.
        'points' => '/([\d][\d,.]*)\h*(?:\/\h*[\d,.]+\h*)?points\b/i',
        'reviews' => '/^\h*Reviews\h*\R\h*([\d][\d,.]*)\h*$|([\d][\d,.]*)\h+reviews\b/mi',
        'photos' => '/^\h*Photos\h*\R\h*([\d][\d,.]*)\h*$|([\d][\d,.]*)\h+photos\b/mi',
        'videos' => '/^\h*Videos\h*\R\h*([\d][\d,.]*)\h*$|([\d][\d,.]*)\h+videos\b/mi',
        'photo_views' => '/([\d][\d,.]*)\h+views\b/i',
        'places_added' => '/^\h*Places\h+added\h*\R\h*([\d][\d,.]*)\h*$|([\d][\d,.]*)\h+places?\h+added\b/mi',
        'roads_added' => '/^\h*Roads\h+added\h*\R\h*([\d][\d,.]*)\h*$|([\d][\d,.]*)\h+roads?\h+added\b/mi',
    ],

    /*
    | The contributor's name. The page title is usually just "Google Maps", so
    | the stats panel heading is tried first, then the line above the level
    | badge, then the title (minus " - Google Maps").
    */
    'name_patterns' => [
        '/^\h*Contributions\s+from\s+(.+?)\h*$/mi',
        '/^\h*([^\n]{1,200}?)\h*\R\h*Local\s+Guide\s*[·•]?\s*Level\s+\d/mi',
    ],
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
