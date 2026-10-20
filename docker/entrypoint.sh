#!/bin/sh
set -e

cd /var/www/html

# Dev convenience: first boot installs PHP deps into the vendor volume.
# Only the `app` container does this; workers wait for app to become healthy.
if [ "${APP_BOOTSTRAP:-0}" = "1" ]; then
    # .env first: composer's post-autoload-dump runs artisan (package discovery,
    # Filament asset publishing).
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] installing composer dependencies..."
        composer install --no-interaction --prefer-dist
    fi
    # Filament's public assets are gitignored; publish them on a fresh clone.
    if [ ! -d public/js/filament ]; then
        php artisan filament:assets
    fi
    if ! grep -q '^APP_KEY=base64' .env; then
        php artisan key:generate --force
    fi
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
fi

if [ "${APP_OPTIMIZE:-0}" = "1" ]; then
    php artisan optimize
    php artisan filament:optimize || true
fi

exec "$@"
