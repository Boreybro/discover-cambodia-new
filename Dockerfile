FROM php:8.3-apache

# PHP extensions: Postgres driver
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql pgsql \
 && a2dismod -f mpm_event mpm_worker || true \
 && a2enmod mpm_prefork rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Serve ./public, allow .htaccess, never run PHP from uploads
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf 'ServerName localhost\n<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n<Directory /var/www/html/public/uploads>\n  <FilesMatch "\\.(php|phtml|phar)$">\n    Require all denied\n  </FilesMatch>\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
 && a2enconf app \
 && printf 'upload_max_filesize=8M\npost_max_size=40M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/app.ini

COPY . /var/www/html
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh && mkdir -p /var/www/html/storage /var/www/html/public/uploads \
 && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads
CMD ["/start.sh"]
