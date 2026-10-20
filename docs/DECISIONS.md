# Decisions

Choices made where the spec was ambiguous or the environment forced a call. Newest at the bottom.

## Infrastructure

- **`vendor/` and `node_modules/` live in named Docker volumes**, not on the bind mount. Bind mounts from Windows/macOS hosts are slow for the thousands of small files Laravel loads per request. Trade-off: IDEs don't see `vendor/` unless you run `composer install` on the host too (optional, IDE-only).
- **First-boot bootstrap in the `app` entrypoint** (`APP_BOOTSTRAP=1`): installs Composer deps into the volume, copies `.env`, generates `APP_KEY`. Workers (`horizon`, `scheduler`) wait for `app` to be healthy. This is what makes `make up && make fresh` work with no manual steps.
- **Vite in Docker uses polling** (`usePolling`) because inotify events don't cross the Windows/macOS bind mount.
- **Separate `bdlg_testing` database** (created by `docker/mysql/init.sql`) so `make test` never wipes dev data.
- **`dev.ps1`** mirrors the Makefile for Windows hosts without `make`.
- **Fonts are TTF, not WOFF2.** Google Fonts serves WOFF2 split into per-script subsets (Bengali/Latin), which would mean several files per family for `FontFace` loading. A single full TTF per weight is simpler and also gets installed as a system font for Chromium (server-side render). Files: `public/fonts/*.ttf` (OFL licensed, licenses alongside).

## Data

- `local_guides.status` has an extra **`inactive`** value for the admin "Mark inactive" action (excluded from the daily sync and leaderboard, distinct from `failed`).
- `frame_generations` has extra columns: **`is_featured`** (admin-curated homepage gallery; generated images never appear on the home page without review) and **`sources_purged_at`** (set when original uploads are deleted after 30 days).
- Extra tables: **`settings`** (key/value JSON for admin-editable home page content) and **`stat_overrides`** (audit trail for manual stat overrides).
- Snapshot dates use **Asia/Dhaka** calendar days, matching the 23:00 Dhaka sync.

## Sync engine

- **Rate limiting blocks instead of releasing.** `GoogleMapsThrottle` job middleware waits for the named `google-maps` limiter instead of releasing the job: Laravel's `RateLimited` middleware releases the job, which counts as an attempt and would burn the 3 tries reserved for real failures. With 1–2 browser workers, blocking is cheap.
- **Every failed attempt is logged** (sync_logs row, `fail_count++`); the 3rd consecutive failure flips the status to `failed`. A later success resets it.
- **Consent/captcha pages count as failures**, not "restricted", so a blocked scrape never hides a public profile.
- **Partial parses never erase data**: only stats that were found are written; missing ones keep their previous value (`null` stays `null`).
- **Waiting for the profile text instead of network-idle.** Google Maps keeps streaming tiles and never reaches network-idle (the first live test took 42 s, near the 45 s timeout). The fetcher waits until the body mentions "Local Guide" / "points" / "Restricted", then applies `LG_SYNC_WAIT_MS`. Typical render time is about 6.5 s.
- **Opening the "Contributions from …" panel.** The public page shows only level, points and photo/view counts. Clicking the level badge reveals every stat with a label (reviews, videos, places and roads added). Photo views exist only as an aria-label, so numeric aria-labels are appended to the parsed text. The real rendered format differs from the spec's starting regexes (`Local Guide Level 8`, `20,232 / 50,000 points`, label-then-number lines); the patterns were rewritten from a real capture, and the spec's inline wording is kept as a fallback (see `docs/PARSER.md`).
- **The restricted-profile fixture is synthetic.** No real restricted profile was available to capture. Replace it with a real capture when one is known.

## Public site

- **Community home page at `/`** (user request), so the **leaderboard moved to `/leaderboard`**. The home page lists open event frames (links to the public editor `/frames/{slug}/create`) and a gallery of generated frames. The gallery only shows generations an admin has marked **featured**, so nobody's photos appear on the homepage without review.
- **Hero text and community links are editable in Admin → Home page** (`settings` table, cached).
- **OG/Twitter tags are rendered in the Blade root template** from a `meta` page prop. Inertia's `<Head>` only runs client-side, and WhatsApp and Facebook crawlers don't execute JavaScript.
- **Unknown values show as "—"** everywhere, and `null` is always sorted last on the leaderboard.
- **The leaderboard cache is versioned** (`leaderboard:v{n}:…` keys) rather than tagged, so one increment invalidates every sort/page combination. It is bumped after the daily batch, after admin changes, and when a brand-new profile first becomes active.
- **Guests can trigger `POST /guides/{id}/refresh`.** It is throttled (10/hour per IP), only queues anything when stats are older than 24 h, and the job is unique per guide.

## Frame generator

- **Client-side rendering with Konva is the primary path** (as specified). The optional **server renderer reuses the same React/Konva component**: Chromium loads a signed internal page (`/internal/frames/{frame}/render`) and reads back the PNG. Client output, server output and the admin preview therefore can't drift apart.
- **Artwork and temp uploads are served through the app (same origin)**, so the canvas is never tainted and no S3 CORS configuration is needed. Final PNGs are public S3/CloudFront objects under `public/` (needed for `og:image`).
- **Storage layout:** `public/frames/**` and `public/generations/*.png` are public. `tmp/{user}/` holds un-finalized uploads (private, signed URLs, deleted after 24 h). `originals/{ulid}/` holds source photos (private, deleted after 30 days).
- **The server validates the uploaded PNG's real size** against the frame's native size, and only accepts the member's own profile and their own temp uploads.
- **Text slot geometry:** `x` is the anchor for the alignment (left edge, centre or right edge) and `y` is the top of the line. Photo slot rotation is around the slot centre. Documented in `docs/FRAME_CONFIG.md`.
- **Text slots referencing an unknown value are hidden**, whether or not they are marked `optional`, so "null", "0" or "-" is never printed.
- **The sample frame artwork is rendered from HTML with Chromium** (`resources/views/frames/sample-*.blade.php`). Imagick's text drawing doesn't shape Bengali conjuncts correctly.
- **The admin live preview shows the saved version** (an iframe of the signed render page). The visual drag-to-place slot editor (a stretch goal) is **not implemented**; slots are edited as JSON, with validation errors that name the exact path.

## Admin

- **The admin panel is English-only** (`AdminLocale` middleware); the public site defaults to Bangla.
- **No create/edit forms for Local Guides.** Profiles enter via "Add by URL" (Module A) and change via sync. Manual edits go through "Override stats", which requires an audit note (`stat_overrides`).

## Environment notes (this repo's dev machine)

- **MinIO's own images are no longer published** on Docker Hub or quay.io. `docker-compose.yml` uses the frozen `bitnamilegacy/minio` build (still MinIO, and it ships `mc`). For production, use AWS S3.
- **Host ports:** Windows reserves 3205–3404, so MySQL is forwarded on 13306. If port 80 is taken, set `APP_PORT=8080` and `APP_URL=http://localhost:8080` in `.env`.
- **CI runs PHP checks inside the dev image** (with service containers on the host network), so Chromium, fonts and imagick/libheif match local exactly.
