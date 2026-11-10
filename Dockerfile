FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends librabbitmq-dev libssh-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /app
RUN rm -rf /var/www/html && ln -s /app/public /var/www/html \
    && mkdir -p /app/var/requests \
    && chown -R www-data:www-data /app/var

WORKDIR /app
