# syntax=docker/dockerfile:1
# Local (compose target=development) y Railway (stage production).
FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache \
    git \
    curl \
    bash \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM base AS development
ENV APP_ENV=local
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

COPY . .
RUN mkdir -p \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/app/public \
      bootstrap/cache \
    && composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
    && chmod -R ug+rwx storage bootstrap/cache \
    && php artisan package:discover --ansi || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["web"]
