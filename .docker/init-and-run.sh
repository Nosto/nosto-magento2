#!/bin/bash -x

# Turn on monitor mode, required by job control
set -m

# Put apache2 running in the bacground as installer needs to access it
apache2-foreground &

if [ ! -f /var/www/html/magento2/.installed ]; then
  sleep 10
  cd /var/www/html/magento2/bin
  if [ "$USE_SSL" == "true" ] ; then
    echo "SetEnvIf X-Forwarded-Proto https HTTPS=on" >> /var/www/html/magento2/.htaccess
    magento setup:install \
      --timezone "Europe/Helsinki" \
      --currency "EUR" \
      --db-host "mysql" \
      --db-name "${MYSQL_ENV_MYSQL_DATABASE}" \
      --db-user "${MYSQL_ENV_MYSQL_USER}" \
      --db-password "${MYSQL_ENV_MYSQL_PASSWORD}" \
      --base-url "https://${VIRTUAL_HOST}/" \
      --use-rewrites 1 \
      --use-secure 1 \
      --base-url-secure "https://${VIRTUAL_HOST}/" \
      --use-secure-admin 1 \
      --admin-firstname "Nosto" \
      --admin-lastname "Solutions" \
      --admin-email "devnull@nosto.com" \
      --admin-user "${ADMIN_USER}" \
      --admin-password "${ADMIN_PASSWORD}" \
      --backend-frontname "admin"
  else
    magento setup:install \
      --timezone "Europe/Helsinki" \
      --currency "EUR" \
      --db-host "mysql" \
      --db-name "${MYSQL_ENV_MYSQL_DATABASE}" \
      --db-user "${MYSQL_ENV_MYSQL_USER}" \
      --db-password "${MYSQL_ENV_MYSQL_PASSWORD}" \
      --base-url "http://${VIRTUAL_HOST}/" \
      --use-rewrites 1 \
      --use-secure 0 \
      --base-url-secure "" \
      --use-secure-admin 0 \
      --admin-firstname "Nosto" \
      --admin-lastname "Solutions" \
      --admin-email "devnull@nosto.com" \
      --admin-user "${ADMIN_USER}" \
      --admin-password "${ADMIN_PASSWORD}" \
      --backend-frontname "admin"
  fi

  cd /var/www/html/magento2/
  composer update --no-dev
  composer config --unset repositories.0
  composer require --update-no-dev nosto/module-nostotagging:@stable
  composer config repositories.0 composer https://repo.magento.com

  chown -R www-data:www-data /var/www/html/magento2

  chmod +x bin/magento

  bin/magento cache:clean
  bin/magento module:enable --clear-static-content Nosto_Tagging
  bin/magento setup:upgrade
  touch pub/static/deployed_version.txt
  bin/magento deploy:mode:set production
  touch /var/www/html/magento2/.installed
  chown -R www-data:www-data /var/www/html/magento2
fi

# Invoke the cron daemon
cron &
# Bring apache2 back to foreground
fg %1