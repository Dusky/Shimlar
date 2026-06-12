FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Enable Apache mod_rewrite (may be useful)
RUN a2enmod rewrite

# Set docroot to httpdocs
ENV APACHE_DOCUMENT_ROOT /var/www/html/httpdocs
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy application code
COPY httpdocs /var/www/html/httpdocs
COPY incz /var/www/html/incz

# Create log directory
RUN mkdir -p /var/log/shimlar && chown -R www-data:www-data /var/log/shimlar

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
