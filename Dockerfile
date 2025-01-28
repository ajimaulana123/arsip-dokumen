FROM php:8.1-apache

# Install ekstensi MySQL dan dependencies
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Konfigurasi Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN a2enmod rewrite

# Copy aplikasi
COPY . /var/www/html/

# Set permission
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache config untuk Railway
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/docker-php.conf \
    && a2enconf docker-php

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]