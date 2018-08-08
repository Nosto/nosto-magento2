All notable changes to this project will be documented in this file. This project adheres to Semantic Versioning.

### 2.11.0
* Add possibility to remove “/pub/” directory from product image URLs
* Add possibility to define quantity for Nosto’s add to cart
* Obey the alternative image tagging feature flag

### 2.10.6
* Fix add to cart (Recobuy) javascript errors when Magento 2 is configured to minimize, merge or bundle javascript files  

### 2.10.5
* Fix product availability check for non visible products in the website 

### 2.10.4
* Fix issue that send null prices when configurable product has no available SKU

### 2.10.3
* Fix Nosto product prices to obey the tax display setting
* Fix product indexer database issue with large catalogs

### 2.10.2
* Add fallback for product URL builder in case the rewrites are missing

### 2.10.1
* Fix issue with product service not handling model filter when Nosto product builder returns null

### 2.10.0
* Add possibility to disable Nosto's multi currency features
* Fix Nosto iframe loading bug

### 2.9.0
* Add advanced setting to disable sending customer data to Nosto servers

### 2.8.0
* Add marketing permission for customer tagging and for buyer (GDPR compatibility) 
* Fix the Nosto account installation screen if no products are attached in a store view

### 2.7.0
Improvements
* Prefix order numbers to avoid collision with already used order numbers when migrating from Magento 1 to Magento 2
* Add possibility to exclude products from Nosto index
* Improve the add to cart popup trigger

Bug fixes
* Fix the list price for bundled products when all selections are optional

Refactoring 
* Use repositories instead of factories where applicable
* Render Nosto tagging programmatically without templates

### 2.6.1
* Improve add to cart popup trigger
* Add support for removing / discontinuing products in Nosto
* Apply catalog price rules for product API calls

### 2.6.0
* Add support for sending cart updates to nosto when product is added to the shopping cart

### 2.5.0
* Add setting for hiding store codes from URLs
* Add a button links to the nosto configuration page
* Add sku id in the cart and order tagging
* Add custom fields tagging to product
* Fix the issue that order and product importing to nosto did not work in php strict mode

### 2.4.0
* Add CI definitions
* Fix doc blocks & coding standard issues
* Clear page cache and layout cache after Nosto account is installed, reconnected or removed
* Introduce repository for Nosto Customer
* Fix infinite redirect loop on Nosto admin page
* Rename price helper methods to avoid confusion whether the taxes are included or not

### 2.3.10
* Fix the issue with sku availability being always in stock

### 2.3.9
* Remove debug logging for database queries

### 2.3.8
* Fix the issue that current currency tagging is missing

### 2.3.7
* Enable sku tagging by default

### 2.3.6
* Update related products by indexer if catalog price rule, review and rating or inventory level get updated
* Update parent product by indexer if its child product has been deleted

### 2.3.5
* Fix indexer bugs

### 2.3.4
* Fix tracking issue of adding sku to cart

### 2.3.3
* Fix exception handling bug

### 2.3.2
* Fix Nosto customer saving

### 2.3.1
* Define arguments for custom logger
* Handle non-existent categories in product category builder
* Move the second recommendation slot under the search results on search results page

### 2.3.0
Improvements
* Introduce update queue and custom indexer for Nosto product updates
* Add cron job to update advanced scheduled pricing (Community edition only)
* Add a feature flag for low stock notifications
* Report Nosto exception to New Relic if available
* Send more contact details to Nosto during the order confirmation
* Omit inventory level and margin determination when product model is used for tagging

### 2.2.3
Bug fixes
* Ignore disabled variations from price calculation and SKU tagging

### 2.2.2
Bug fixes
* Fix the discount calculation in order items

### 2.2.1
Bug fixes
* Fix the price handling for configurable products with no SKUs
* Fix product observer to fetch the parent configurable product
* Check if product is scheduled / staged before calling Nosto API

### 2.2.0
Improvements
* Fix deprecated layout definitions
* Display more descriptive errors when account opening fails
* Rename restore cart controller to avoid routing issues with case sensitive setup
* Add missing positioning attributes to layout definitions
* Add "Do not edit" notifications to template files
* Send product updates to Nosto when ratings and reviews are updated or added

Bug fixes
* Fix Nosto product handling in multi-store setup
* Recover from invalid account opening requests
* Remove default variation id from account opening if multiple currencies are not used

### 2.1.0
Improvements
* Add support for restore cart link
* Add possibility to add product attributes to Nosto tags
* Add support for indicating low stock for a product
* Add support for using thumb url
* Include Magento's object for events dispatched by Nosto

Bug fixes
* Set area code outside constructor in product sync command
* Remove multi-currency check from product template
* Check Nosto account before rendering the javascript stub
* Add null checks item builders

### 2.0.1
* Fix the multi-currency variation issue when only single currency is used

### 2.0.0
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
