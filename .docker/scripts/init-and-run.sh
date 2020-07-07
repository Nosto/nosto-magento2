#!/bin/bash -x

# Turn on monitor mode, required by job control
set -m

if [ ! -f /var/www/html/magento2/.installed ]; then
  cd /var/www/html/magento2/ || exit
  composer require nosto/php-sdk:@stable

  until mysql -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" -h"${MYSQL_HOST}"; do
    >&2 echo "MySQL is unavailable - sleeping"
    sleep 5
  done

  bin/magento setup:install \
      --timezone "Europe/Helsinki" \
      --currency "EUR" \
      --db-host "${MYSQL_HOST}" \
      --db-name "${MYSQL_DATABASE}" \
      --db-user "${MYSQL_USER}" \
      --db-password "${MYSQL_PASSWORD}" \
      --base-url "http://${VIRTUAL_HOST}/" \
      --use-rewrites 1 \
      --use-secure 0 \
      --base-url-secure "https://${VIRTUAL_HOST}/" \
      --use-secure-admin 0 \
      --admin-firstname "John" \
      --admin-lastname "Doe" \
      --admin-email "Johndoe@example.com" \
      --admin-user "${ADMIN_USER}" \
      --admin-password "${ADMIN_PASSWORD}" \
      --backend-frontname "admin" \
      --amqp-host="${RABBITMQ_HOST}" \
      --amqp-port="5672" \
      --amqp-user="guest" \
      --amqp-password="guest" \
      --amqp-virtualhost="/"
  
  chmod +x bin/magento
  bin/magento deploy:mode:set developer

  bin/magento setup:upgrade

  # Set global / non-module related options
  bin/magento config:set admin/security/session_lifetime 86400
  bin/magento config:set general/region/state_required TV
  bin/magento config:set general/country/default FI
  bin/magento config:set shipping/origin/country_id FI
  bin/magento config:set tax/defaults/country FI
  bin/magento config:set general/region/display_all 0

  # Enable JS & CSS minifying
  bin/magento config:set dev/js/merge_files 1
  bin/magento config:set dev/js/enable_js_bundling 1
  bin/magento config:set dev/js/minify_files 1
  bin/magento config:set dev/css/merge_css_files 1
  bin/magento config:set dev/css/minify_files 1

  # Indexer configuration
  bin/magento indexer:set-mode schedule

  bin/magento cache:flush
#  bin/magento cache:enable

  touch pub/static/deployed_version.txt
  touch /var/www/html/magento2/.installed
fi

# Invoke the cron daemon
sudo cron &
# Start apache
sudo apache2-foreground
