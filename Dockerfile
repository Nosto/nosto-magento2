FROM php:7.0-apache

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

MAINTAINER Nosto "platforms@nosto.com"

# Utils
RUN apt-get update && apt-get -y install unzip wget libfreetype6-dev libjpeg-dev libmcrypt-dev libreadline-dev libpng-dev libicu-dev mysql-client libmcrypt-dev libxml2-dev libxslt1-dev vim nano git nano tree

# PHP AST
RUN cd /tmp && \
    git clone https://github.com/nikic/php-ast.git && \
    cd php-ast && \
    phpize && \
    ./configure && \
    make install && \
    docker-php-ext-enable ast && \
    rm -rf /tmp/php-ast

# Supervisor
RUN apt-get install -y software-properties-common python-pip supervisor

# MySQL
RUN echo "mysql-server-5.5 mysql-server/root_password password root" | debconf-set-selections
RUN echo "mysql-server-5.5 mysql-server/root_password_again password root" | debconf-set-selections

RUN apt-get -y install mysql-server
RUN chown -R mysql /var/lib/mysql

# PHP extensions
RUN docker-php-ext-install gd mcrypt intl xsl zip bcmath

COPY .docker/supervisord/supervisord.conf /etc/
COPY .docker/index.php /var/www/html/
COPY .docker/init-and-run.sh /usr/bin/init-and-run
RUN chmod +x /usr/bin/init-and-run

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ \
    --with-jpeg-dir=/usr/include/ && \
    docker-php-ext-install gd mysqli pdo_mysql mcrypt mbstring soap xsl zip && \
    php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

WORKDIR "/var/www/html/"
RUN composer create-project magento/community-edition

WORKDIR "/var/www/html/community-edition"
RUN chmod +x bin/magento
#RUN bin/magento setup:install \
#      --timezone "Europe/Helsinki" \
#      --currency "EUR" \
#      --db-host "localhost" \
#      --db-name "magento2" \
#      --db-user "root" \
#      --db-password "root" \
#      --base-url "http://localhost/" \
#      --use-rewrites 1 \
#      --use-secure 0 \
#      --base-url-secure "" \
#      --use-secure-admin 0 \
#      --admin-firstname "Admin" \
#      --admin-lastname "Admin" \
#      --admin-email "devnull@xxxnotarealdomain.com" \
#      --admin-user "admin" \
#      --admin-password "Admin12345" \
#      --backend-frontname "admin"
#
#RUN composer update --no-dev
#RUN composer config --unset repositories.0
#RUN composer require --update-no-dev nosto/module-nostotagging:@stable
#RUN composer config repositories.0 composer https://repo.magento.com
#RUN chown -R www-data:www-data /var/www/html/community-edition
#RUN bin/magento cache:clean
#RUN bin/magento module:enable --clear-static-content Nosto_Tagging
#RUN bin/magento setup:upgrade
#RUN touch pub/static/deployed_version.txt
#RUN bin/magento deploy:mode:set production
#RUN touch .installed
#RUN chown -R www-data:www-data /var/www/html/community-edition

EXPOSE 443 80 3306
ENTRYPOINT ["supervisord", "-n", "-c", "/etc/supervisord.conf"]