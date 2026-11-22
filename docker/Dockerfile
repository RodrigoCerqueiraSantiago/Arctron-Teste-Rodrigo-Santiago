FROM composer:2 AS composer

FROM php:8.3-fpm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql sockets zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && touch .env \
    && composer dump-autoload --no-interaction --optimize \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
