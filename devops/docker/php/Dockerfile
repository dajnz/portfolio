# Stage 1: Build dependencies and optimize
FROM php:8.5-fpm-alpine AS build

RUN apk add --no-cache \
        $PHPIZE_DEPS \
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
    && pecl install redis \
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

RUN apk add --no-cache \
        $PHPIZE_DEPS \
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
    && pecl install redis \
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
