#!/bin/sh
set -e

mkdir -p var/cache/prod var/log public
chown -R www-data:www-data /var/www/html/var /var/www/html/public

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting Nginx and PHP-FPM..."
nginx &
exec php-fpm