FROM php:8.5-cli-alpine

# `chromium` (+ fonts) is the e2e browser for the dev container: Playwright's
# own downloads are glibc builds that cannot run on this musl image (the
# kernel fails exec with ENOENT). The e2e harness launches it via
# PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH (tests/e2e/playwright.config.ts).
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev libwebp-dev libavif-dev freetype-dev libxml2-dev oniguruma-dev \
        git openssh unzip bash jq nodejs npm age linux-headers \
        chromium fontconfig ttf-dejavu \
    && pecl install redis xdebug \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml zip bcmath intl gd exif pcntl \
    && docker-php-ext-enable redis xdebug

ENV PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium-browser

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY devops/docker/dev/xdebug.ini /usr/local/etc/php/conf.d/
COPY devops/docker/dev/php.ini /usr/local/etc/php/conf.d/
RUN adduser -D -u 1000 portfolio
# Git runs as the container user, but the bind-mounted repo is owned by the
# host user (uid 1000) — without this, git refuses the repo ("dubious
# ownership"), which breaks `make commitlint` inside the dev container. The
# SYSTEM config covers both the root build-time user and the runtime
# portfolio user (the container now defaults to it).
RUN git config --system --add safe.directory /var/www/html
WORKDIR /var/www/html
# The dev container runs as the local user (uid 1000 = the host user) so
# npm/composer/artisan artifacts keep the host's ownership; use
# `docker compose exec -u root dev <cmd>` for root operations.
USER portfolio
CMD ["sleep", "infinity"]
