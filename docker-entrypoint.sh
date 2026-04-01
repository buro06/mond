#!/bin/bash
service cron start

chown www-data:www-data /var/www/html/db

# Install Composer dependencies on first start (vendor is not committed)
if [ ! -d /var/www/html/vendor/phpmailer ]; then
    cd /var/www/html && composer install --no-interaction --quiet
fi

exec "$@"
