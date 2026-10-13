FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x deploy/start.sh
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/production.ini
EXPOSE 80
CMD ["/var/www/html/deploy/start.sh"]
