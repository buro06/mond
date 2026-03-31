FROM php:8.3-apache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y libsqlite3-dev libzip-dev cron \
    && docker-php-ext-install pdo_sqlite zip

RUN a2enmod rewrite headers

COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

RUN printf 'SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin\n* * * * * www-data /usr/local/bin/php /var/www/html/cron.php >> /var/log/mond.log 2>&1\n' \
    > /etc/cron.d/mond \
    && chmod 0644 /etc/cron.d/mond \
    && touch /var/log/mond.log \
    && chown www-data:www-data /var/log/mond.log

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
