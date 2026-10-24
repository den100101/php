
FROM php:8.2-apache

# Install required PHP database extensions
RUN docker-php-ext-install mysqli pdo_mysql

# Enable Apache URL rewriting
RUN a2enmod rewrite

# Configure Apache to listen on Render's default port
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
    /etc/apache2/sites-available/000-default.conf

# Set the application directory
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html/

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Render's default web service port
EXPOSE 10000

CMD ["apache2-foreground"]
