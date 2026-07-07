FROM php:8.2-cli

WORKDIR /app

# Install system deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libcurl4-openssl-dev pkg-config \
    && docker-php-ext-install zip curl

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy all files EXCEPT .env — environment variables come from docker-compose.yml
COPY . .
RUN rm -f .env

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
