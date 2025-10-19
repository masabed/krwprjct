FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    zip unzip curl git libpng-dev libonig-dev libxml2-dev libzip-dev \
    libmagickwand-dev --no-install-recommends \
    && docker-php-ext-install pdo_mysql mbstring zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Set PHP upload limits
RUN echo "upload_max_filesize=50M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
