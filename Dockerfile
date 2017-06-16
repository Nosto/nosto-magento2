FROM composer/composer:latest

ENV LANGUAGE en_US.UTF-8
ENV LANG en_US.UTF-8
ENV TERM xterm

RUN apt-get update && \
    apt-get -y -qq install nano tree zlib1g-dev libicu-dev g++

RUN cd /tmp && \
    git clone https://github.com/nikic/php-ast.git && \
    cd php-ast && \
    phpize && \
    ./configure && \
    make install && \
    docker-php-ext-enable ast && \
    docker-php-ext-install intl && \
    rm -rf /tmp/php-ast
