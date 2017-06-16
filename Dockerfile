FROM composer/composer:latest

ENV LANGUAGE en_US.UTF-8
ENV LANG en_US.UTF-8
ENV TERM xterm

RUN apt-get update && \
    apt-get -y -qq install nano tree git-core zlib1g-dev libicu-dev g++

RUN git config --system user.name Docker && \
    git config --system user.email docker@localhost

RUN cd /tmp && \
    git clone https://github.com/nikic/php-ast.git && \
    cd php-ast && \
    phpize && \
    ./configure && \
    make install && \
    docker-php-ext-enable ast && \
    docker-php-ext-install intl && \
    rm -rf /tmp/php-ast

RUN groupadd -r plugins -g 113 
RUN useradd -ms /bin/bash -u 113 -r -g plugins plugins
USER plugins
