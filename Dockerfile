FROM composer:2 AS composer

FROM php:8.4-cli-alpine AS app

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl opcache pdo_mysql

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN touch .env

COPY composer.json composer.lock symfony.lock ./
COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY translations ./translations
COPY migrations ./migrations
COPY assets ./assets
COPY importmap.php ./

RUN APP_SECRET=build-time-secret \
    DATABASE_URL='mysql://app:app@127.0.0.1:3306/app?serverVersion=8.4.0&charset=utf8mb4' \
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

RUN APP_SECRET=build-time-secret \
    DATABASE_URL='mysql://app:app@127.0.0.1:3306/app?serverVersion=8.4.0&charset=utf8mb4' \
    php bin/console asset-map:compile --env=prod --no-debug

COPY docker/entrypoint.sh /usr/local/bin/shopping-list-entrypoint

RUN chmod +x /usr/local/bin/shopping-list-entrypoint \
    && mkdir -p var/cache var/log public/uploads/covers \
    && chown -R www-data:www-data var public/uploads

USER www-data

EXPOSE 8000

ENTRYPOINT ["shopping-list-entrypoint"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
