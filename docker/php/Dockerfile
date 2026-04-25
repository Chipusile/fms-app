FROM php:8.3-fpm-bookworm

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    gd \
    bcmath \
    mbstring \
    opcache \
    pcntl

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 22 (for asset building)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# PHP configuration for local development
COPY php-local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www/backend

# Create application user matching host UID for file permission compatibility
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} fms && useradd -u ${UID} -g fms -m fms \
    && chown -R fms:fms /var/www

USER fms

EXPOSE 9000

CMD ["php-fpm"]
