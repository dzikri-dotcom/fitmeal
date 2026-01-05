FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    libpng-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo pdo_mysql zip intl gd \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
