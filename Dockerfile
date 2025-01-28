# Dockerfile
FROM php:8.1-apache

# Install ekstensi MySQL dan dependencies
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy aplikasi
COPY . /var/www/html/

# Set permission
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]