FROM php:8.2-cli

WORKDIR /app

# Install system deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libcurl4-openssl-dev pkg-config \
    && docker-php-ext-install zip curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy all files EXCEPT .env — environment variables come from docker-compose.yml
COPY . .
RUN rm -f .env

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
