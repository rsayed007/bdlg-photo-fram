# Profile parser — how it works and how to fix it when Google changes the UI

There is no official API for Local Guide profiles. The public contributor page
is rendered by JavaScript, so we load it in headless Chromium and parse its
visible text with regular expressions that live in **`config/localguides.php`**.
When Google changes wording or layout, you normally only need to edit those
patterns (no code change), add a fixture and run the tests.

## Pipeline

```
SyncLocalGuide (queue: sync-priority | sync)
  └─ GoogleMapsThrottle      4/min (LG_SYNC_PER_MINUTE) + 5–15 s jitter
  └─ BrowsershotProfileFetcher
       1. open https://www.google.com/maps/contrib/{id}/contribute?hl=en&gl=bd
       2. wait until the body text mentions "Local Guide" / "points" /
          "Restricted" (Maps never reaches network-idle), then LG_SYNC_WAIT_MS
       3. click the "Local Guide Level N" badge → the
          "Contributions from <name>" panel opens with every stat labelled
       4. return innerText + numeric aria-labels ("8,591,611 views")
  └─ ProfileTextParser       named regexes from config/localguides.php
```

## What the page looks like (October 2026)

Main page (always visible):

```
Roman Syed
Local Guide Level 8
20,232 / 50,000 points          ← first number = points, second = next level
Reviews
Photos
 1,436  8,591,611               ← photo count + views (labels only in aria)
```

Stats panel (after clicking the level badge):

```
Contributions from Roman Syed
20,232 / 50,000 points

Reviews
282

Photos
1,473

Videos
140
...
Places added
53

Roads added
0
```

Aria-labels appended by the fetcher: `1,436 photos`, `8,591,611 views`.

Note: the panel's **Photos** (all contributed photos) is slightly higher than
the header count (currently published photos). The panel value is used when
present.

## Rules

- Each pattern's **first non-empty capture group** is the value; patterns may
  have alternatives (`A|B`) for different layouts.
- Commas/dots are stripped (`20,232` → `20232`).
- **No match → `null` ("unknown"), never 0.** A literal `0` on the page
  (e.g. `Roads added\n0`) is a real zero and is stored.
- A partial parse never erases data: only the stats that were found are
  written; others keep their previous values.
- **Restricted:** neither level nor points found → status `restricted`,
  previous stats are kept.
- **Blocked:** text containing a `blocked_markers` entry (consent wall,
  captcha) is a *failed* attempt, not restricted.
- **Name:** `name_patterns` (panel heading, then the line above the level
  badge), then the page title minus ` - Google Maps`.
- Inline alternatives use `\h` (same-line whitespace) so a number on the line
  *above* a label (e.g. `Ratings\n2,201\n\nPhotos`) is never captured.

## When Google changes the page

1. Render the profile and look at the text:

   ```bash
   make shell
   php artisan guides:fetch 107121503923715912205 --text
   ```

   The table shows what each pattern extracts. `--text` prints the raw text.

2. Save it as a fixture (anonymize if it is not a consenting member):

   ```bash
   php artisan guides:fetch 107121503923715912205 --save=my-new-layout
   # → tests/Fixtures/profiles/my-new-layout.txt
   ```

3. Adjust `patterns` / `name_patterns` / `blocked_markers` in
   `config/localguides.php`. Prefer *adding* an alternative (`old|new`) so
   older fixtures keep passing.

4. Add a test case in `tests/Unit/ProfileTextParserTest.php` for the new
   fixture and run `make test`.

5. If the panel stops opening (all of reviews/videos/places are `null` but
   level and points work), the badge selector in
   `BrowsershotProfileFetcher::EXTRACT_SCRIPT` needs updating.

6. Check admin → **Sync logs**: each entry stores the first 2,000 characters
   of the rendered text (`raw_excerpt`) for debugging. Full HTML is never
   stored.

## Fixtures

| file | source | covers |
|---|---|---|
| `107121503923715912205.txt` | real capture (test profile) | full panel |
| `main-page-only.txt` | real capture, panel not opened | fallback path, unknown ≠ 0 |
| `top-level-no-target.txt` | synthetic | level 10, no next-level target, real 0 |
| `inline-legacy.txt` | synthetic | spec-style inline wording |
| `restricted.txt` | synthetic | restricted profile (replace with a real capture when available) |
| `consent.txt` | synthetic | consent wall → failure |

## Politeness

- Max 4 requests/minute (`LG_SYNC_PER_MINUTE`), 1–2 browser processes
  (`LG_SYNC_PROCESSES`), random 5–15 s jitter between jobs.
- One daily batch at 23:00 Asia/Dhaka; on-demand syncs only when stats are
  older than 24 h.
