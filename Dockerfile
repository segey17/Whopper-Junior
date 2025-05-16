FROM php:apache

# Установка системных зависимостей, необходимых для расширений PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

# Установка расширений PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip sockets sodium

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование composer.json
COPY composer.json ./

# Установка зависимостей Composer
# --ignore-platform-reqs может быть полезен, если есть несоответствия версий PHP/расширений
# --no-interaction для неинтерактивного режима
# --no-plugins и --no-scripts для безопасности и если скрипты не нужны на этапе сборки
# --prefer-dist для загрузки дистрибутивов вместо клонирования исходного кода
RUN composer install --ignore-platform-reqs --no-interaction --no-plugins --no-scripts --prefer-dist

# Копирование остальных файлов проекта
COPY . .

# Настройка прав доступа, если это необходимо (часто для uploads, cache и т.д.)
# Пример: RUN chown -R www-data:www-data /var/www/html/uploads

# Включаем mod_rewrite для Apache (если используется, например, для ЧПУ)
RUN a2enmod rewrite

# Apache по умолчанию слушает порт 80
EXPOSE 80

# Команда по умолчанию не требуется, так как php:apache уже настроен на запуск Apache
# CMD ["apache2-foreground"]
