FROM php:8.2-fpm

# System libs & headers for PHP extensions + ImageMagick + Ghostscript
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    zip \
    unzip \
    pkg-config \
    build-essential \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libmagickwand-dev \
    ghostscript \
    imagemagick \
 && if [ -f /etc/ImageMagick-6/policy.xml ]; then \
      # longgarkan policy untuk PDF (dari "none" jadi "read|write")
      sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' /etc/ImageMagick-6/policy.xml; \
    elif [ -f /etc/ImageMagick/policy.xml ]; then \
      sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' /etc/ImageMagick/policy.xml; \
    fi \
 && rm -rf /var/lib/apt/lists/*

# Enable GD with freetype/jpeg/webp
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd intl bcmath exif opcache

# PECL: imagick & redis
RUN pecl install imagick redis \
 && docker-php-ext-enable imagick redis

# PHP limits
RUN { \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'memory_limit=512M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www
EXPOSE 9000
CMD ["php-fpm"]
