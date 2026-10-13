# Use official PHP runtime with Apache
FROM php:8.2-apache

# Enable Apache rewrite module if needed for clean URLs
RUN a2enmod rewrite

# Copy all project files into the Apache web root
COPY . /var/www/html/

# Set proper write permissions for upload and json data directories
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Expose standard web port
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]