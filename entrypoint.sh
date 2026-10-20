#!/bin/sh
set -e

mkdir -p var/cache var/log public
chown -R www-data:www-data var public

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting Nginx and PHP-FPM..."
nginx &
exec php-fpm