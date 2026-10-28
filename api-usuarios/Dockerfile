FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
 && a2enmod headers rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'ServerTokens Prod\nServerSignature Off\n<Directory /var/www/html/public>\n    Options -Indexes\n</Directory>\n' \
      > /etc/apache2/conf-available/seguridad-extra.conf \
 && a2enconf seguridad-extra

COPY . /var/www/html/
