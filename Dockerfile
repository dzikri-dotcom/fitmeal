# Stage 1: Build dependencies
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist

# Stage 2: Runtime
FROM php:8.2-fpm

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
