FROM php:8.2-apache

# Extensión MySQL y módulos de Apache
RUN docker-php-ext-install mysqli \
 && a2enmod headers rewrite

# Solo la carpeta public/ es accesible desde la web; src/ (código) queda fuera
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Endurecimiento: php.ini de producción, sin versión en cabeceras, sin listado de directorios
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'ServerTokens Prod\nServerSignature Off\n<Directory /var/www/html/public>\n    Options -Indexes\n</Directory>\n' \
      > /etc/apache2/conf-available/seguridad-extra.conf \
 && a2enconf seguridad-extra

COPY . /var/www/html/
