FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && a2enmod headers deflate expires rewrite \
    && rm -rf /var/lib/apt/lists/* \
    && printf '%s\n' \
       'upload_max_filesize=5M' \
       'post_max_size=28M' \
       'max_file_uploads=5' \
       'max_input_time=60' \
       > /usr/local/etc/php/conf.d/portfolio-uploads.ini

WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && chmod 0644 /var/www/html/*.php /var/www/html/*.html

COPY docker-entrypoint.sh /usr/local/bin/portfolio-entrypoint
RUN chmod +x /usr/local/bin/portfolio-entrypoint

EXPOSE 3000
ENTRYPOINT ["portfolio-entrypoint"]
