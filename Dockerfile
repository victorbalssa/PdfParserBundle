FROM php:7.1-apache

ENV DEBIAN_FRONTEND noninteractive

# Configure Apache and installs other services
RUN a2enmod rewrite \
    && apt-get update \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && apt-get install -y curl git cron libpng-dev \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install extra php libraries
RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get install -y zlib1g-dev libzip-dev poppler-utils
RUN docker-php-ext-install zip
RUN docker-php-ext-install gd

# Add custom Apache config file
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html/
EXPOSE 80

CMD apachectl -D FOREGROUND
