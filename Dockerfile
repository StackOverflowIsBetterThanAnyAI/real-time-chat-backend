FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    curl \
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
    dom

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]