# Minimal PHP-FPM baseline with opcache enabled (fair production config).
FROM php:8.4-fpm-alpine
RUN docker-php-ext-enable opcache
COPY opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
