# syntax=docker/dockerfile:1.7
# ── Base: PHP 8.4 CLI with everything both dev and prod need ──────────────────
FROM php:8.4-cli-alpine AS base

# Runtime libraries stay; the -dev headers are installed as a virtual package so
# they can be dropped again without taking the shared objects gd/zip/intl link
# against with them.
RUN apk add --no-cache \
        git curl unzip bash tzdata mysql-client \
        icu-libs libzip libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS linux-headers \
        icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath intl zip gd opcache pcntl sockets exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-network .build-deps \
    && php -m | grep -q gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/api/php.ini /usr/local/etc/php/conf.d/99-metacreator.ini

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1

# ── Dev: source is bind-mounted, Xdebug available on demand ───────────────────
FROM base AS dev

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && apk del --no-network .xdebug-deps
# Xdebug is installed but NOT enabled: enable with XDEBUG_MODE=debug at runtime.
COPY docker/api/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini.disabled

# vendor/ lives in a named volume, so it starts empty on a fresh checkout. The
# entrypoint installs into it before handing over to the real command.
COPY docker/api/dev-entrypoint.sh /usr/local/bin/dev-entrypoint
RUN chmod +x /usr/local/bin/dev-entrypoint

ENV PHP_IDE_CONFIG=serverName=metacreator
EXPOSE 8080
ENTRYPOINT ["dev-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]

# ── Build: install prod dependencies and warm caches ──────────────────────────
FROM base AS build

COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY apps/api/ ./
RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

# ── Production runtime ────────────────────────────────────────────────────────
FROM base AS production

ENV APP_ENV=production APP_DEBUG=false
COPY docker/api/opcache-prod.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY --from=build /app /app

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
    && chown -R app:app /app/storage /app/bootstrap/cache
USER app

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") ? 0 : 1);'
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
