# Use PHP 8.3 with Apache as base image
FROM php:8.3-apache

# deb.debian.org is Fastly CDN (146.75.x.x) — blocked by Iranian ISP on both port 80 and 443.
# ftp.debian.org is a direct Debian server (non-Fastly) and security.debian.org already works.
RUN printf 'Types: deb\nURIs: http://ftp.debian.org/debian\nSuites: trixie trixie-updates\nComponents: main\n\nTypes: deb\nURIs: http://security.debian.org/debian-security\nSuites: trixie-security\nComponents: main\n' \
    > /etc/apt/sources.list.d/debian.sources

# Install build-time C libraries needed for PHP extensions
# libfreetype-dev + libjpeg62-turbo-dev are required for imagettftext() in CaptchaService
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure GD with FreeType + JPEG support before installing
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Compile PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Copy Composer binary from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy only what Apache needs at runtime (not webUI source)
COPY hesabixCore/ /var/www/html/hesabixCore/
COPY public_html/ /var/www/html/public_html/

RUN mkdir -p /var/www/html/hesabixCore/var/sessions \
              /var/www/html/hesabixCore/var/log \
              /var/www/html/hesabixCore/var/lock \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
WORKDIR /var/www/html/hesabixCore
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
