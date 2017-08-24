FROM ubuntu:14.04

ENV LANGUAGE en_US.UTF-8
ENV LANG en_US.UTF-8
ENV TERM xterm
ENV MYSQL_ENV_MYSQL_DATABASE magento2
ENV MYSQL_ENV_MYSQL_USER root
ENV MYSQL_ENV_MYSQL_ROOT root
ENV MAGENTO_ADMIN_USER admin
ENV MAGENTO_ADMIN_PASSWORD Admin12345
ENV VIRTUAL_HOST localhost
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV DEBIAN_FRONTEND noninteractive

MAINTAINER Nosto "platforms@nosto.com"

# Utils
RUN apt-get update && apt-get -y install unzip wget libfreetype6-dev libjpeg-dev libmcrypt-dev libreadline-dev libpng-dev libicu-dev mysql-client libmcrypt-dev libxml2-dev libxslt1-dev vim nano git nano tree

# Supervisor
RUN apt-get install -y software-properties-common

RUN apt-get install -y language-pack-en-base
RUN LC_ALL=en_US.UTF-8 add-apt-repository ppa:ondrej/php

# Apache & PHP & MySQL
RUN apt-get update && apt-get -y install apache2 php7.0 mysql-client-core-5.6 mysql-server-core-5.6 mysql-server-5.6
RUN a2enmod rewrite

# PHP extensions
RUN apt-get install -y php7.0-dev php7.0-gd php7.0-mcrypt php7.0-intl php7.0-xsl php7.0-zip php7.0-bcmath php7.0-curl php7.0-mbstring php7.0-mysql
RUN cd /tmp && \
    git clone https://github.com/nikic/php-ast.git && \
    cd php-ast && \
    phpize && \
    ./configure && \
    make install && \
    rm -rf /tmp/php-ast

COPY .docker/index.php /var/www/html/
COPY .docker/init-and-run /usr/bin/init-and-run
RUN chmod +x /usr/bin/init-and-run
COPY .docker/php/extensions/* /etc/php/7.0/mods-available/
RUN rm /etc/apache2/sites-enabled/*
COPY .docker/apache/* /etc/apache2/sites-enabled
RUN ln -s /etc/php/7.0/mods-available/ast.ini /etc/php/7.0/apache2/conf.d/20-ast.ini
RUN ln -s /etc/php/7.0/mods-available/ast.ini /etc/php/7.0/cli/conf.d/20-ast.ini

RUN php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

WORKDIR "/var/www/html/"
RUN composer create-project magento/community-edition
RUN chmod +x /var/www/html/community-edition/bin/magento

# Magento 2 sample data
RUN mkdir /var/www/html/sample-data
WORKDIR "/var/www/html/sample-data"
RUN git clone https://github.com/magento/magento2-sample-data .
RUN git checkout tags/2.1.8

WORKDIR "/"
EXPOSE 443 80 3306
ENTRYPOINT ["init-and-run"]