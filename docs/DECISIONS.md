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
