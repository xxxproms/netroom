# syntax=docker/dockerfile:1

# --- Front-end assets -------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# --- PHP dependencies -------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Runtime ----------------------------------------------------------------
FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql intl zip bcmath opcache

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

RUN addgroup -g 1000 netroom \
    && adduser -u 1000 -G netroom -s /bin/sh -D netroom \
    && chown -R netroom:netroom /app/storage /app/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER netroom

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
