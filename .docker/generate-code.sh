#!/bin/bash -x

# Run this from the root dir of the repo with ./.docker/generate-code.sh
docker run -d -it --name mage_code_gen \
        --volume=$(pwd):/var/www/html/magento/app/code/Nosto/Tagging \
        --volume=$(pwd)/generated_output:/var/www/html/magento2/generated supercid/magento-base:2.3.4

docker exec -w /var/www/html/magento2 -it mage_code_gen bin/magento module:enable --all
docker exec -w /var/www/html/magento2 -it mage_code_gen bin/magento setup:di:compile

docker rm -f mage_code_gen
