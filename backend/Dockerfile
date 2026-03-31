FROM php:8.3-cli-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        postgresql-client \
        libpq-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/entrypoint.sh /usr/local/bin/fms-entrypoint

RUN chmod +x /usr/local/bin/fms-entrypoint

ENTRYPOINT ["fms-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
