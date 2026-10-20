FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    icu-dev \
    oniguruma-dev \
    zip \
    unzip \
    postgresql-dev

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    opcache \
    intl \
    mbstring \
    xml \
    ctype \
    iconv

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80

CMD sh -c "php bin/console cache:clear --env=prod && nginx && php-fpm"