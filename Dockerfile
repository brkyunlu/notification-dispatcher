FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies (include PHPIZE_DEPS for PECL extensions like pcov)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    supervisor \
    $PHPIZE_DEPS

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install PCOV for code coverage (php artisan test --coverage)
RUN pecl install pcov && docker-php-ext-enable pcov

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u 1000 -d /home/notificationuser notificationuser
RUN mkdir -p /home/notificationuser/.composer && \
    chown -R notificationuser:notificationuser /home/notificationuser

# Copy existing application directory permissions
COPY --chown=notificationuser:notificationuser . /var/www

# Change current user to notificationuser
USER notificationuser

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
