# nosto-magento2-extension

## Changelog

* 1.0.0-RC
    * Rename the package to nosto/module-nostotagging
    
* 0.2.0
    * Dispatch event after Nosto product is loaded
    * Improve exception handling
    * Fix acl issues
        
* 0.1.1
    * Fix the composer files to autoload Nosto PHP SDK correctyl

* 0.1.0
    * First implementation of Magento 2 extension

## Known issues
* Customer and cart tagging are not working in product pages due to Magento 2 bug [#3202](https://github.com/magento/magento2/issues/3202)

## Missing features
* admin config section for "Advanced Settings"
* account update API
* currency exchange rate API
* currency exchange rate CRON

