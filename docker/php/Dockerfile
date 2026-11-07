FROM php:8.2-fpm

# Установить системные зависимости
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка дополнительных расширений PHP
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install ftp

# Установить рабочую директорию
WORKDIR /app/public

# Установка прав доступа
RUN chown -R www-data:www-data /app/public
