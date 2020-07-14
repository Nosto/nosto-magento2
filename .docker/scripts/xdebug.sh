#!/usr/bin/env bash

# Run as www-data with `sudo -E ./xdebug.sh`

if [ "$#" -ne 1 ]; then
    SCRIPT_PATH=$(basename "$0")
    echo "Usage: $SCRIPT_PATH on|off"
    exit 1;
fi

if [ "$1" == "on" ]; then
    docker-php-ext-enable xdebug && \
    echo "xdebug.default_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_port=9002" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_connect_back=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.remote_autostart=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.max_nesting_level=1500" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

    echo "Xdebug enabled"
fi

if [ "$1" == "off" ]; then
  rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
  echo "Xdebug disabled"
fi

/etc/init.d/apache2 reload
