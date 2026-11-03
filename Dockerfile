# ==========================================
# Stage 1: Build Frontend Assets with Vite
# ==========================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json eslint.config.js ./

RUN npm run build

# ==========================================
# Stage 2: Base PHP Image
# ==========================================
FROM php:8.3-fpm-bookworm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    supervisor \
    default-mysql-client \
    procps \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        posix \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer from official image
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ==========================================
# Stage 3: Production Image
# ==========================================
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

# Copy Composer files and install production dependencies
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy application source code
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources/views ./resources/views
COPY routes ./routes
COPY artisan ./artisan
COPY docker ./docker

# Copy built frontend assets from Stage 1
COPY --from=frontend-builder /app/public/build ./public/build

# Generate optimized Composer autoloader
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev

# Copy PHP, PHP-FPM, Supervisor, and Entrypoint configurations
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/99-billflow.ini
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Create required directories, set ownership and permissions
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod +x docker/supervisor/start-queue.sh \
    && chmod +x artisan \
    && mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

