<?php

// Integration tests bootstrap file
use Magento\Framework\App\Bootstrap;

// Bootstrap setup for Magento integration tests
$bootstrapParams = $_SERVER;
// Set flag to run without installation
$bootstrapParams[Bootstrap::PARAM_REQUIRE_MAINTENANCE_MODE] = false;
$bootstrapParams[Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = false;

// This assumes the tests are run from the Magento root directory
$magentoRootDir = getcwd();
if (!file_exists($magentoRootDir . '/app/etc/di.xml')) {
    // If not being run from Magento root, adjust path
    if (file_exists(__DIR__ . '/../../../../app/etc/di.xml')) {
        $magentoRootDir = realpath(__DIR__ . '/../../../..');
    } elseif (file_exists(__DIR__ . '/../../../../../app/etc/di.xml')) {
        $magentoRootDir = realpath(__DIR__ . '/../../../../..');
    }
}

require $magentoRootDir . '/app/bootstrap.php';
$bootstrap = Bootstrap::create($magentoRootDir, $bootstrapParams);

$objectManager = $bootstrap->createObjectManager();
$objectManager->configure([
    'preferences' => [],
]);
