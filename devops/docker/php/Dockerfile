# Stage 1: Build dependencies and optimize
FROM php:8.5-fpm-alpine AS build

# redis is built from a PINNED GitHub release tarball (6.3.0) instead of
# `pecl install redis`: the pecl.php.net channel/CDN is flaky and
# intermittently fails builds with "No releases available for package
# pecl.php.net/redis". Pinning also makes image builds deterministic (the
# version matches what the previous pecl-based build resolved).
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        curl \
        fcgi \
        procps \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libavif-dev \
        freetype-dev \
        libxml2-dev \
        oniguruma-dev \
    && mkdir -p /tmp/build/redis \
    && curl -fsSL -o /tmp/build/redis.tar.gz \
        https://github.com/phpredis/phpredis/archive/refs/tags/6.3.0.tar.gz \
    && tar -xzf /tmp/build/redis.tar.gz -C /tmp/build/redis --strip-components=1 \
    && cd /tmp/build/redis \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && cd / \
    && rm -rf /tmp/build \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install \
        pdo pdo_pgsql mbstring xml zip bcmath intl gd exif pcntl \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Stage 2: Production runtime — only built artifacts
FROM php:8.5-fpm-alpine

# The fpm pool runs as the host user (dev: bind-mounted storage/ writes;
# prod: internal uid — builds pass no args and default to 1000, keeping the
# baked artifacts' ownership consistent). Overridden via HOST_UID/HOST_GID
# build args (docker-compose.yml), required on hosts whose user is not
# uid 1000 (e.g. a VM).
ARG HOST_UID=1000
ARG HOST_GID=1000
# Xdebug is a non-production-only debugging aid: the dev compose passes "1",
# production omits it and the default 0 keeps the image unchanged. When set,
# the extension source build must run here — before docker-php-ext-install,
# which purges $PHPIZE_DEPS (ADR-0027/B41).
ARG INSTALL_XDEBUG=0

COPY devops/docker/xdebug.ini /tmp/xdebug.ini

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        curl \
        fcgi \
        procps \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libavif-dev \
        freetype-dev \
        libxml2-dev \
        oniguruma-dev \
    && mkdir -p /tmp/build/redis \
    && curl -fsSL -o /tmp/build/redis.tar.gz \
        https://github.com/phpredis/phpredis/archive/refs/tags/6.3.0.tar.gz \
    && tar -xzf /tmp/build/redis.tar.gz -C /tmp/build/redis --strip-components=1 \
    && cd /tmp/build/redis \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && cd / \
    && rm -rf /tmp/build \
    && if [ "$INSTALL_XDEBUG" = "1" ]; then \
        apk add --no-cache linux-headers \
        && mkdir -p /tmp/build/xdebug \
        && curl -fsSL -o /tmp/build/xdebug.tar.gz \
            https://github.com/xdebug/xdebug/archive/refs/tags/3.5.3.tar.gz \
        && tar -xzf /tmp/build/xdebug.tar.gz -C /tmp/build/xdebug --strip-components=1 \
        && cd /tmp/build/xdebug \
        && phpize \
        && ./configure \
        && make -j"$(nproc)" \
        && make install \
        && cd / \
        && rm -rf /tmp/build \
        && docker-php-ext-enable xdebug \
        && cp /tmp/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; \
    fi \
    && rm -f /tmp/xdebug.ini \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install \
        pdo pdo_pgsql mbstring xml zip bcmath intl gd exif pcntl \
    && docker-php-ext-enable redis
RUN addgroup -g ${HOST_GID} portfolio \
    && adduser -D -u ${HOST_UID} -G portfolio portfolio \
    && sed -i 's/^user = .*/user = portfolio/; s/^group = .*/group = portfolio/' /usr/local/etc/php-fpm.d/www.conf
WORKDIR /var/www/html
COPY --from=build --chown=portfolio:portfolio /var/www/html .

# Production php.ini overrides — upload limits must accommodate the largest payload
# (backup restore uploads via admin UI, up to backup_upload_max_size). The max size
# is configurable per env; set upload_max_filesize and post_max_size to at least
# 128M (the default backup_upload_max_size is 100 MB).
COPY devops/docker/php/php.ini /usr/local/etc/php/conf.d/php.ini
