FROM php:8.3-cli

# Cài extension cần thiết
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql

# Cài Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập working dir
WORKDIR /var/www

# Copy code
COPY . .

# Cài dependencies
RUN composer install --no-dev --optimize-autoloader

# Tạo thư mục cần thiết
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

# Cấp quyền
RUN chown -R www-data:www-data /var/www

# Expose port
EXPOSE 8000

# Start server — tôn trọng $PORT của Render (shell form để mở rộng ${PORT}),
# tăng giới hạn upload (ảnh base64 + multipart frame/sticker) và timeout
CMD php -d upload_max_filesize=64M -d post_max_size=128M -d max_execution_time=120 \
    -S 0.0.0.0:${PORT:-8000} -t public public/index.php