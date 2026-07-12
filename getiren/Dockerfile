FROM php:8.4-fpm

# Sistem bağımlılıkları + PHP eklentileri
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip intl pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (resmi imajdan kopyala)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# php-fpm 9000 portunda dinler
EXPOSE 9000
CMD ["php-fpm"]
