# Gunakan image PHP versi terbaru
FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Salin seluruh file aplikasi ke dalam container
COPY . /var/www/html/

# Convert all PHP filenames to lowercase
RUN cd /var/www/html && \
    find . -name "*.php" -exec sh -c ' \
    filename=$(basename "{}"); \
    dir=$(dirname "{}"); \
    lowercase=$(echo "$filename" | tr "[:upper:]" "[:lower:]"); \
    mv "$dir/$filename" "$dir/$lowercase" 2>/dev/null || true \
    ' \;

# Install ekstensi PHP yang diperlukan (misalnya, untuk MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set permission agar Apache dapat mengakses file
RUN chown -R www-data:www-data /var/www/html

# Ekspose port 80 untuk akses web
EXPOSE 80

# Jalankan Apache di foreground
CMD ["apache2-foreground"]