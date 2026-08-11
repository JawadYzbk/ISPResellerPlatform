#!/bin/sh

set -eu

if [ ! -f vendor/autoload.php ] || [ composer.json -nt vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction
fi

php artisan migrate --force --no-interaction

cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
