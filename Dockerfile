# Development-only image for this CodeIgniter 4 app. Production stays on
# cPanel per guides/DEPLOYMENT.md; this is not meant to be deployed as-is
# (no opcache preload tuning, no multi-stage slim build, debug-friendly).
FROM php:8.3-fpm

# Extensions the app actually uses, beyond what php:fpm ships:
# - mysqli: database driver (app/Config/Database.php)
# - intl, mbstring: composer.json hard requirements
# - gd, exif: image uploads/gallery (PublicImageStorage, EXIF orientation fix)
# - zip: composer itself needs it to install/update packages
# - curl: MidtransClient, CURLRequest, geoip:update command
# - dom, xml: TemplateHtmlSanitizer uses DOMDocument for the upload allowlist
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libonig-dev libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli intl mbstring exif gd zip curl dom xml \
    && rm -rf /var/lib/apt/lists/*
# Note: the -dev packages above are kept installed (not purged). Several of
# their runtime shared libraries (libonig, libzip, libicu, libxml2, ...) are
# what the compiled extensions link against; apt's autoremove cannot tell
# that a compiled .so still needs them, so purging after install would
# silently break these extensions at runtime. The -dev headers add a few MB;
# the segfault they'd otherwise prevent is not worth chasing in a dev image.

# Composer itself, pinned by copying the official image's binary rather than
# piping installer scripts from the network.
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies from the lock file only, in a layer that is cached as
# long as composer.json/composer.lock don't change — editing application
# code does not invalidate this layer or force a reinstall.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader --no-dev \
    && composer clear-cache

# Now bring in the rest of the application and finish the autoloader, which
# needs the full app/ tree to discover classes.
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Run as an unprivileged user instead of the image's default root, and own
# the paths CI4 writes to at runtime (cache/logs/session/uploads are
# separate named volumes in compose.yaml, so only their mountpoints need
# fixing here; the bind-mounted source tree keeps the host's ownership).
RUN useradd --uid 1000 --create-home --shell /bin/bash app \
    && mkdir -p writable/cache writable/logs writable/debugbar writable/session public/uploads \
    && chown -R app:app writable public/uploads
USER app

EXPOSE 9000

CMD ["php-fpm"]
