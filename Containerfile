FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y $PHPIZE_DEPS libicu-dev libzip-dev unzip git \
    && pecl install xdebug \
    && docker-php-ext-install mysqli pdo pdo_mysql intl zip \
    && docker-php-ext-enable xdebug \
    && a2enmod rewrite \
    && { \
        echo "xdebug.mode=coverage"; \
        echo "xdebug.start_with_request=no"; \
    } > /usr/local/etc/php/conf.d/zz-xdebug.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Route all requests through CodeIgniter's public directory.
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
