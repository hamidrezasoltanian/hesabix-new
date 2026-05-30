# Use PHP 8.3 with Apache as base image
FROM php:8.3-apache

# Switch apt to HTTPS — port 80 to deb.debian.org may be filtered
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || true && \
    sed -i 's|http://security.debian.org|https://security.debian.org|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || true

# Install only build-time C libraries needed for PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Compile PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Copy Composer binary from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy only what Apache needs at runtime (not webUI source)
COPY hesabixCore/ /var/www/html/hesabixCore/
COPY public_html/ /var/www/html/public_html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
WORKDIR /var/www/html/hesabixCore
RUN composer install --no-interaction --optimize-autoloader --no-dev

WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
