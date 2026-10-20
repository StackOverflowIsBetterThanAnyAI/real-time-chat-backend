FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev

RUN docker-php-ext-install pdo pdo_pgsql opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80

CMD sh -c "php bin/console cache:clear --env=prod && nginx && php-fpm"