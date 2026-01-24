FROM php:8.2-fpm-alpine

# Arguments for user configuration
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    linux-headers \
    netcat-openbsd \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        dom \
        ctype \
        fileinfo \
        intl \
        bcmath \
        zip \
        pcntl \
        gd \
        opcache \
        sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install su-exec for dropping privileges
RUN apk add --no-cache su-exec

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create application user to match host user (for file permissions)
RUN addgroup -g ${GROUP_ID} laravel \
    && adduser -u ${USER_ID} -G laravel -s /bin/sh -D laravel

# Set working directory
WORKDIR /var/www/html

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Don't switch to non-root user here - entrypoint will handle it
# This allows fixing volume permissions on startup

EXPOSE 9000

CMD ["php-fpm"]
