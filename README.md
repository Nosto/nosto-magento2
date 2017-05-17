# Nosto extension for Magento 2

## Changelog

### 2.0.0-RC2
* Namespace fixes

### 2.0.0-RC1
* Add possibility to use following attributes in Nosto 
  * GTIN
  * brand
  * inventory level
  * supplier cost
  * rating
  * review count
  * alternative image URLs
* Add possibility to extend / override product data
* Add support for multi-currency stores
* Add possibility to choose which image version is tagged
* Add support for handling the qualification UI
* Add check if Nosto account is installed before outputting Nosto tags & scripts
* Add page type tagging
* Add support for customer reference
* Implement support for "Add to cart" button for recommendations
* Update account settings over the API to Nosto
* Fix product price issue with special prices
* Fix product price to obey tax rules 
* Fix product URL sent via API to Nosto
* Fix list price issue configurable products
* Update to the latest Nosto PHP SDK

### 1.2.0
* Stable release
* Stable release

### 1.2.0-RC2
* Updated the composer lock
* Removed the minimum stability flag

### 1.2.0-RC1
* Bump to SDK version 2.5.2
* Make compatible with MEQP
    
### 1.1.1
* Fix the errors when running Magento compiler
* Change the block definition of referenceContainer to referenceBlock
    
### 1.1.0
* Use Knockout.js for dynamic cart and customer tagging in order to handle full page cache correctly
    
### 1.0.1
* Add "js stub" for Nosto script
* Fix issue with orders when Nosto module is installed but Nosto account is not connected

### 1.0.0
* Make the plug-in compatible with Magento 2.1.0

### 1.0.0-RC4
* Remove variation tagging
    
### 1.0.0-RC3
* Fix store resolving issue(#18)
    
### 1.0.0-RC2
* Fix javascript include issue (#16)
* Fix multi store issue (#15)

### 1.0.0-RC
* Rename the package to nosto/module-nostotagging
    
### 0.2.0
* Dispatch event after Nosto product is loaded
* Improve exception handling
* Fix acl issues
        
### 0.1.1
* Fix the composer files to autoload Nosto PHP SDK correctly

### 0.1.0
* First implementation of Magento 2 extension
