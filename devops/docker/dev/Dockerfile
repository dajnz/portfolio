FROM php:8.5-cli-alpine

# `chromium` (+ fonts) is the e2e browser for the dev container: Playwright's
# own downloads are glibc builds that cannot run on this musl image (the
# kernel fails exec with ENOENT). The e2e harness launches it via
# PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH (tests/e2e/playwright.config.ts).
#
# redis and xdebug are built from PINNED GitHub release tarballs (6.3.0 /
# 3.5.3) instead of `pecl install`: the pecl.php.net channel/CDN is flaky
# and intermittently fails builds with "No releases available for package
# pecl.php.net/redis". Pinning also makes image builds deterministic (the
# versions match what the previous pecl-based build resolved).
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        curl \
        postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev libwebp-dev libavif-dev freetype-dev libxml2-dev oniguruma-dev \
        git openssh unzip bash jq nodejs age linux-headers \
        chromium fontconfig ttf-dejavu \
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
    && rm -rf /tmp/build/redis /tmp/build/redis.tar.gz \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml zip bcmath intl gd exif pcntl \
    && docker-php-ext-enable redis xdebug

ENV PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium-browser

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# pnpm is the Node package manager (pinned in package.json `packageManager`).
# The binary is copied from the official musl image so the dev image no longer
# needs npm.
COPY --from=ghcr.io/pnpm/pnpm:12.15.0-alpine /opt/pnpm /opt/pnpm
RUN ln -s /opt/pnpm/pnpm /usr/local/bin/pnpm
COPY devops/docker/xdebug.ini /usr/local/etc/php/conf.d/
COPY devops/docker/dev/php.ini /usr/local/etc/php/conf.d/
# The container runs as the host user so pnpm/composer/artisan artifacts keep
# the host's ownership on the bind-mounted repo. The uid/gid default to 1000
# and are overridden via the HOST_UID/HOST_GID build args (compose
# interpolates them from the environment/.env — see docker-compose.yml) —
# required on hosts whose user is not uid 1000 (e.g. a VM), where a baked
# uid 1000 cannot write the bind mount.
ARG HOST_UID=1000
ARG HOST_GID=1000
# `.composer` is the mount point of the `composer_cache` named volume
# (docker-compose.yml). Creating it here, portfolio-owned, means a FIRST-MOUNT
# EMPTY volume is seeded from the image with the correct ownership — otherwise
# the empty volume is root-owned and the first non-root `composer install`
# dies with EACCES.
RUN addgroup -g ${HOST_GID} portfolio \
    && adduser -D -u ${HOST_UID} -G portfolio portfolio \
    && mkdir -p /home/portfolio/.composer \
    && chown portfolio:portfolio /home/portfolio/.composer
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
