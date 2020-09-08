#!/bin/bash -x
BRANCH_NAME=$(git branch --show-current)
PROJECT_NAME=$(cat composer.json| jq --raw-output .name)
APP_DIR=$(pwd)
TMP_DIR=$(mktemp -d -t ci-XXXXXXXXXX)
cd $TMP_DIR || exit 0
composer create-project magento/community-edition=2.3.2 .
composer config minimum-stability dev
composer config prefer-stable true
composer require --no-update ${PROJECT_NAME}:dev-${BRANCH_NAME}
composer update --no-dev
bin/magento module:enable --all
bin/magento setup:di:compile
mv -f generated $APP_DIR/vendor/magento/generated
