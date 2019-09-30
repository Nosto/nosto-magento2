<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\TestFramework\Bootstrap;

return [
    'db-host' => '127.0.0.1:3336',
    'db-user' => 'user',
    'db-password' => 'password',
    'db-name' => 'magento2_integration_tests',
    'db-prefix' => '',
    'backend-frontname' => 'backend',
    'admin-user' => Bootstrap::ADMIN_NAME,
    'admin-password' => Bootstrap::ADMIN_PASSWORD,
    'admin-email' => Bootstrap::ADMIN_EMAIL,
    'admin-firstname' => Bootstrap::ADMIN_FIRSTNAME,
    'admin-lastname' => Bootstrap::ADMIN_LASTNAME,
    'amqp-host' => 'localhost',
    'amqp-port' => '5672',
    'amqp-user' => 'guest',
    'amqp-password' => 'guest',
];
