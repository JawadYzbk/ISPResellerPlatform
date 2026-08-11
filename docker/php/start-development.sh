#!/bin/sh

set -eu

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views

if [ ! -f vendor/autoload.php ] || [ composer.json -nt vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan config:clear --no-interaction

cd public
exec php -d opcache.validate_timestamps="${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-0}" -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
