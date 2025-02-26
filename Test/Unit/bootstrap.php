<?php

// Unit tests bootstrap file
require_once __DIR__ . '/../../vendor/autoload.php';

// Mock Magento autoloader
class Magento_Autoloader {
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }
    
    public static function autoload($class) {
        // This is a simplified mock implementation
        return false;
    }
}

// Register mock autoloader
Magento_Autoloader::register();
