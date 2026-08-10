FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pcntl zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
