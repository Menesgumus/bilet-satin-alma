# PHP-FPM için base image
FROM php:8.2-fpm

# Sistem güncellemeleri ve gerekli paketleri yükle
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_sqlite \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install exif \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install bcmath

# Composer'ı yükle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Uygulama dosyalarını kopyala
COPY . .

# Composer bağımlılıklarını yükle
RUN composer install --no-dev --optimize-autoloader

# Gerekli dizinleri oluştur ve izinleri ayarla
RUN mkdir -p storage \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Nginx konfigürasyonunu kopyala
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Supervisor konfigürasyonunu kopyala
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Port 80'i aç
EXPOSE 80

# Supervisor'ı başlat
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
