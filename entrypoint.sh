#!/bin/sh
set -e

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting Nginx and PHP-FPM..."
nginx &
exec php-fpm