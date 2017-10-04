FROM ubuntu:14.04

ENV LANGUAGE en_US.UTF-8
ENV LANG en_US.UTF-8
ENV TERM xterm
ENV MYSQL_ENV_MYSQL_DATABASE magento2
ENV MYSQL_ENV_MYSQL_USER root
ENV MYSQL_ENV_MYSQL_ROOT root
ENV MAGENTO_ADMIN_USER admin
ENV MAGENTO_ADMIN_PASSWORD Admin12345
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV DEBIAN_FRONTEND noninteractive

# Satis credentials for repo.magento.com to download the community edtition
ARG         repouser=569521a9babbeda71b5cb25ce40168a3
ARG         repopass=ef77d5e321fec542f3102e2059f3d192

# Environment variables to force the extension to connect to a specified instance
ENV         NOSTO_SERVER_URL staging.nosto.com
ENV         NOSTO_API_BASE_URL https://staging.nosto.com/api
ENV         NOSTO_OAUTH_BASE_URL https://staging.nosto.com/oauth
ENV         NOSTO_WEB_HOOK_BASE_URL https://staging.nosto.com
ENV         NOSTO_IFRAME_ORIGIN_REGEXP .*

MAINTAINER  Nosto "platforms@nosto.com"

RUN         export LC_ALL=en_US.UTF-8
# Install all core dependencies required for setting up Apache and PHP atleast
RUN         apt-get update && \
            apt-get -y install unzip && \
            apt-get -y install wget && \
            apt-get -y install libfreetype6-dev && \
            apt-get -y install libjpeg-dev && \
            apt-get -y install libmcrypt-dev && \
            apt-get -y install libreadline-dev && \
            apt-get -y install libpng-dev && \
            apt-get -y install libicu-dev && \
            apt-get -y install mysql-client && \
            apt-get -y install libmcrypt-dev && \
            apt-get -y install libxml2-dev && \
            apt-get -y install libxslt1-dev && \
            apt-get -y install vim && \
            apt-get -y install nano && \
            apt-get -y install git && \
            apt-get -y install nano && \
            apt-get -y install tree && \
            apt-get -y install curl && \
            apt-get -y install software-properties-common && \
            apt-get -y install language-pack-en-base && \
            apt-get -y install supervisor

# Add the custom PHP repository to install the PHP modules. In order to use the
# command to add a repo, the package software-properties-common must be already
# installed
RUN        add-apt-repository ppa:ondrej/php

# Install Apache, MySQL and all the required development and prod PHP modules
RUN        apt-get update && \
           apt-get -y install apache2 && \
           apt-get -y install php7.0 && \
           apt-get -y install mysql-client-core-5.6 && \
           apt-get -y install mysql-server-core-5.6 && \
           apt-get -y install mysql-server-5.6 && \
           apt-get -y install php7.0-dev && \
           apt-get -y install php7.0-gd && \
           apt-get -y install php7.0-mcrypt && \
           apt-get -y install php7.0-intl && \
           apt-get -y install php7.0-xsl && \
           apt-get -y install php7.0-zip && \
           apt-get -y install php7.0-bcmath && \
           apt-get -y install php7.0-curl && \
           apt-get -y install php7.0-mbstring && \
           apt-get -y install php7.0-mysql && \
           apt-get -y install php-ast && \
           apt-get -y install php7.0-soap && \
           a2enmod rewrite && phpenmod ast soap && \
           a2dissite 000-default.conf

RUN php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

RUN        service mysql start && \
           mysql -e "GRANT ALL ON *.* TO 'root'@'localhost' IDENTIFIED BY 'root'" && \
           mysql -h localhost -uroot -proot -e "CREATE SCHEMA IF NOT EXISTS magento2" && \
           cd /var/www/html && \
           composer config --global repositories.0 composer https://repo.magento.com && \
           composer config --global http-basic.repo.magento.com $repouser $repopass && \
           composer create-project magento/community-edition && \
           cd community-edition && \
           composer config --unset minimum-stability && \
           composer config repositories.0 composer https://repo.magento.com && \
           composer config http-basic.repo.magento.com $repouser $repopass && \
           chmod +x bin/magento && \
           bin/magento setup:install \
              --timezone          "Europe/Helsinki" \
              --currency          "EUR" \
              --db-host           "localhost" \
              --db-name           "magento2" \
              --db-user           "root" \
              --db-password       "root" \
              --base-url          "http://localhost/community-edition/" \
              --use-rewrites       1 \
              --use-secure         0 \
              --base-url-secure   "https://localhost/community-edition/" \
              --use-secure-admin   0 \
              --admin-firstname   "Admin" \
              --admin-lastname    "User" \
              --admin-email       "admin@nosto.com" \
              --admin-user        "admin" \
              --admin-password    "Admin12345" \
              --backend-frontname "admin" && \
           bin/magento deploy:mode:set --skip-compilation production && \
           bin/magento sampledata:deploy && \
           bin/magento setup:upgrade && \
           bin/magento setup:di:compile && \
           service mysql stop && \
           chown -R www-data:www-data /var/www/html/community-edition/

# Set the working directory to the Magento installation then install the latest
# version of the extension without the development dependencies. The dependency
# injection is then generated and all static content is deployed.
# Notice that MySQL is also shut down to prevent database corruption.
WORKDIR    /var/www/html/community-edition
RUN        service mysql start && \
           composer require --update-no-dev nosto/module-nostotagging:@stable && \
           bin/magento deploy:mode:set --skip-compilation production && \
           bin/magento module:enable --clear-static-content Nosto_Tagging && \
           bin/magento setup:upgrade && \
           bin/magento setup:di:compile && \
           bin/magento setup:static-content:deploy && \
           service mysql stop && \
           chown -R www-data:www-data /var/www/html/community-edition/

RUN        groupadd -r plugins -g 113 && \
           useradd -ms /bin/bash -u 113 -r -G plugins,www-data plugins
USER       plugins
EXPOSE     443 80
COPY       default.conf     /etc/apache2/sites-enabled
COPY       supervisord.conf /etc/supervisord.conf
ENTRYPOINT ["supervisord", "-c", "/etc/supervisord.conf"]
