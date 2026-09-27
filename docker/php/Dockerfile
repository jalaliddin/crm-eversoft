# Laravel runtime shared by the backend (php-fpm), queue worker and scheduler services.
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype libxml2 tzdata fcgi \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libxml2-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath pcntl opcache exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/backend
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# Development: source is bind-mounted; dependencies installed by the entrypoint.
FROM base AS dev
ENV APP_ENV=local
# Match the host user so files created in the bind-mounted source (logs, cache, uploads) stay editable.
ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data
USER www-data

# Production: dependencies and optimized autoloader baked into the image.
FROM base AS prod
ENV APP_ENV=production
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini
USER www-data
