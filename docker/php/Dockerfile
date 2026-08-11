FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        postgresql-client \
        postgresql-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/development.ini /usr/local/etc/php/conf.d/99-development.ini

WORKDIR /var/www/html

EXPOSE 8000
