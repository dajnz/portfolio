FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        postgresql-dev libzip-dev icu-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev libxml2-dev oniguruma-dev \
        git openssh unzip bash jq nodejs npm age linux-headers \
    && pecl install redis xdebug \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml zip bcmath intl gd pcntl \
    && docker-php-ext-enable redis xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY devops/docker/dev/xdebug.ini /usr/local/etc/php/conf.d/
COPY devops/docker/dev/php.ini /usr/local/etc/php/conf.d/
RUN adduser -D -u 1000 portfolio
WORKDIR /var/www/html
CMD ["sleep", "infinity"]
