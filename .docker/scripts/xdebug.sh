#!/usr/bin/env bash

# Run as www-data

if [ "$#" -ne 1 ]; then
    SCRIPT_PATH=$(basename "$0")
    echo "Usage: $SCRIPT_PATH on|off"
    exit 1;
fi

if [ "$1" == "on" ]; then
    sudo -E docker-php-ext-enable xdebug && \
    sudo echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    sudo echo "xdebug.remote_autostart=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    sudo echo "xdebug.remote_port=9002" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    #sudo echo "xdebug.remote_handler=dbgp" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    sudo echo "xdebug.remote_connect_back=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    sudo echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    sudo echo "xdebug.remote_host=docker.for.mac.localhost" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    echo "Xdebug enabled"
fi

if [ "$1" == "off" ]; then
  sudo rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
  echo "Xdebug disabled"
fi

sudo /etc/init.d/apache2 reload
