# Une seule base de code pour app, queue et scheduler ; web sert les fichiers publics.

# --- PHP et extensions du site ---
FROM php:8.4-fpm-bookworm AS base
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.9.19/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl zip bcmath exif pcntl opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-site.ini
WORKDIR /var/www/html

# --- Dépendances PHP de production ---
FROM base AS vendor
ADD --chmod=0755 https://getcomposer.org/download/2.8.12/composer.phar /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize --classmap-authoritative

# --- Styles et scripts ---
FROM node:20-bookworm-slim AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views vendor/laravel/framework/src/Illuminate/Pagination/resources/views
COPY resources resources
COPY public public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# --- Le site (app, queue, scheduler) ---
FROM base AS app
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build public/build
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/site-entrypoint
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -s ../storage/app/public public/storage \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["site-entrypoint"]
CMD ["php-fpm"]

# --- Le serveur web ---
FROM caddy:2.10-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/Caddyfile /etc/caddy/Caddyfile
