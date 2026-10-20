# syntax=docker/dockerfile:1.7

###############################################################################
# Base: PHP 8.3-FPM + extensions + Chromium + fonts + Node 20 + Puppeteer
###############################################################################
FROM php:8.3-fpm-bookworm AS base

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_HOME=/tmp/composer \
    PUPPETEER_SKIP_DOWNLOAD=true \
    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium \
    BROWSERSHOT_NODE_MODULES=/usr/local/lib/node_modules \
    NODE_PATH=/usr/local/lib/node_modules

# System libraries, Chromium and fonts (Bengali + emoji) in one layer.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates libfcgi-bin \
        libzip-dev libicu-dev libonig-dev libzip4 libicu72 \
        libmagickwand-dev libheif-dev libheif1 imagemagick \
        chromium \
        fonts-noto-core fonts-noto-ui-core fonts-noto-color-emoji fonts-noto-extra \
        fontconfig \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl bcmath zip exif pcntl opcache \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apt-get purge -y --auto-remove libicu-dev libzip-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Hind Siliguri + Poppins as system fonts too, so Chromium (server render) has them.
COPY public/fonts/*.ttf /usr/share/fonts/truetype/bdlg/
RUN fc-cache -f

# Node 20 (copied from the official image) + Puppeteer (uses system Chromium).
COPY --from=node:20-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npm install -g puppeteer@23 \
    && npm cache clean --force

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/healthcheck.sh /usr/local/bin/app-healthcheck
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-healthcheck /usr/local/bin/app-entrypoint

# Run as www-data, remapped to the host uid/gid so bind mounts stay writable.
RUN git config --system --add safe.directory /var/www/html \
    && groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g www-data www-data \
    && mkdir -p /var/www/html/vendor /var/www/html/node_modules /tmp/composer \
    && chown -R www-data:www-data /var/www /tmp/composer

WORKDIR /var/www/html
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 CMD ["app-healthcheck"]

###############################################################################
# Dev: opcache off, source comes from the bind mount.
###############################################################################
FROM base AS dev
COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/zz-opcache.ini
USER www-data

###############################################################################
# Composer deps for production (no dev packages).
###############################################################################
FROM base AS vendor
USER www-data
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

###############################################################################
# Frontend build (Vite). Needs vendor/ for Ziggy's JS route helper.
###############################################################################
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

###############################################################################
# Production image.
###############################################################################
FROM base AS prod
COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini
USER www-data
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf node_modules tests storage/logs/*.log
