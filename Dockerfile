FROM php:7.0-apache

ENV LANGUAGE en_US.UTF-8
ENV LANG en_US.UTF-8
ENV TERM xterm
ENV MYSQL_ENV_MYSQL_DATABASE magento2
ENV MYSQL_ENV_MYSQL_USER root
ENV MYSQL_ENV_MYSQL_ROOT root
ENV ADMIN_USER admin
ENV ADMIN_PASSWORD admin
ENV MAGENTO_VERSION 2.1.6_sample_data-2017-03-29-09-38-47
ENV MAGENTO_ARCHIVE Magento-EE-${MAGENTO_VERSION}
ENV PATH $PATH:/var/www/html/magento2/bin

MAINTAINER Nosto "platforms@nosto.com"

# Utils
RUN apt-get update && \
    apt-get -y -qq install nano tree git

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

COPY .docker/supervisord/supervisord.conf /etc/
COPY .docker/index.php /var/www/html/
COPY .docker/init-and-run.sh /

# Setup Magento 2
RUN cd /var/www/html && mkdir magento2 && cd magento2 && \
    curl -O https://s3.amazonaws.com/nosto-pub/magento/${MAGENTO_ARCHIVE}.tar.gz && \
    tar zxf ${MAGENTO_ARCHIVE}.tar.gz && \
    rm -f ${MAGENTO_ARCHIVE}.tar.gz && \
    find . -type d -exec chmod 770 {} \; && find . -type f -exec chmod 660 {} \; && chmod u+x bin/magento && \
    chown -R www-data:www-data /var/www/html/magento2

EXPOSE 443 80 3306
ENTRYPOINT ["supervisord", "-n", "-c", "/etc/supervisord.conf"]