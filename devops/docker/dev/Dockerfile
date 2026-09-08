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
# The container runs as the host user so npm/composer/artisan artifacts keep
# the host's ownership on the bind-mounted repo. The uid/gid default to 1000
# and are overridden via the HOST_UID/HOST_GID build args (compose
# interpolates them from the environment/.env — see docker-compose.yml) —
# required on hosts whose user is not uid 1000 (e.g. a VM), where a baked
# uid 1000 cannot write the bind mount.
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN addgroup -g ${HOST_GID} portfolio \
    && adduser -D -u ${HOST_UID} -G portfolio portfolio
# Git runs as the container user, but the bind-mounted repo is owned by the
# host user — without this, git refuses the repo ("dubious ownership"),
# which breaks `make commitlint` inside the dev container. The SYSTEM config
# covers both the root build-time user and the runtime portfolio user (the
# container now defaults to it).
RUN git config --system --add safe.directory /var/www/html
WORKDIR /var/www/html
# Use `docker compose exec -u root dev <cmd>` for root operations.
USER portfolio
CMD ["sleep", "infinity"]
