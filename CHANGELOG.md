All notable changes to this project will be documented in this file. This project adheres to Semantic Versioning.

### 4.0.0-rc5 (pre-release)
* Amend fixes from [3.10.1](###3.10.1) (null check for customer)

### 4.0.0-rc4 (pre-release)
* Amend fixes from [3.10.0](###3.10.0) (SKU collection building speed)
* Add a caching layer for storing already built category strings

### 4.0.0-rc3 (pre-release)
* Amend fixes from [3.8.8](###3.8.8) (Magento 2.3.3 compatibility)

### 4.0.0-rc2 (pre-release)
* Add null checks for product data before trying to unserialize the data
* Check the memory consumption in indexers and exit gracefully if the process starts to run out of PHP memory limit
* Improve the error handling in product data generation

### 4.0.0-rc1 (pre-release)
* Introduce cache for Nosto product data to speedup the product tagging added to the product pages
* Introduce Nosto product data change detection to avoid redundant API calls to Nosto
* Utilize bulk operations for product updates
* Add support for indexing in parallel mode
* Cleanup the change log database tables after indexer run
* Prevent full reindex on `setup:upgrade`

### 3.10.1
* Fix an issue with sending order confirmations via API when customer details could not be resolved

### 3.10.0
* Speed-up sku collection building by using native magento method to fetch configurable product SKU's
* Speed-up category name building by using Magento's category collection

Credits also goes to `Ivan Chepurnyi` (https://github.com/IvanChepurnyi) for his performance improvement feedback

### 3.9.0
* Remove category personalization features (separate plug-in)
* Use graphql for sending order confirmations and order status updates
* Speedup the SKU price lookups by using price index table (catalog_product_index_price)
* Speedup the inventory level lookups by using `StockRegistryProvider`
* Add constraint for minimum supported Magento version (2.2.6)

### 3.8.8
* Fix the compatibility issue with Magento 2.3.3 in ratings & reviews building 

### 3.8.7
* Fix product availability when in single store mode

### 3.8.6
* Fix add to cart array comparison

### 3.8.5
* Remove proxy classes from constructors to be in accordance with Magento marketplace code review

### 3.8.4
* Fix Nosto indexer's full reindex logic 

### 3.8.3
* Fix parent product resolving for SKUs in product service

### 3.8.2
* Fix redirect_uri for multi-store setup with different domains

### 3.8.1
* Fix version comparison on data upgrading

### 3.8.0
* Use mock product in category personalisation when preview mode is enabled
* Generate customer_reference value automatically when new customer is created
* Create command line to generate customer_reference value for all customers missing it
* Add inventory level data to SKUs

### 3.7.5
* Remove product cache flushing after upsert to avoid failures in altering product categories

### 3.7.4
* Fix issue with trying to call method on null when order confirmation observer fails

### 3.7.3
* Fix issue with add to cart controller where product from request could be null
* Fetch the customer group directly from the session since the logged in check seems to fail when full page cahce is enabled

### 3.7.2
* Get current store from store code param when connecting existing Nosto account
* Fix unclosed javascript method when Nosto autoload is enabled

### 3.7.1
* Fix issue in setting the marketing permission in customer tagging

### 3.7.0
* Add customer reference field when installing the extension
* Ignore non-numeric product ids in Graphql responses

### 3.6.1
* Use sections for active variation tagging when price variations are enabled
* Exclude base variation from variation collection

### 3.6.0
* Add support for persistent customer reference 
* Enrich the customer tagging to contain more fields

### 3.5.0
* Refactor console commands to use proxy dependencies to avoid redundant dependency injection chain reaction
* Remove redundant injected dependencies from block classes 
* Enrich the category tagging to contain url, image, etc. 

### 3.4.1
* Fix issue with price variations in case catalog rules are specified

### 3.4.0
* Add category personalisation for sorting products
* Fix issue with sending orders when user is not logged in
* Fix issue with sending unmatched orders
* Add date published to product tagging
* Fix issue with redirect url
* Fix issue with reconnecting same account for same scope

### 3.3.0
* Fix an issue with configurable products that were added to cart had no link or image
* Handle exceptions in line cart line item building and order line item building
* Remove support for PHP < 7.0.0

### 3.2.3
* Fix issue with scope when saving the domain

### 3.2.2
* Fix issue with missing storefront domain when upgrading module

### 3.2.1
* Fix issue calculating bundle product price with no options

### 3.2.0
* Disable price variation when multicurrency is enabled
* Skip product attributes that contains arrays with non-scalar values
* Exclude non-scalar arrays from tags selector
* Refactor method being called many times 

### 3.1.0
* Define percentage of PHP memory that indexer is allowed to use
* Prevent indexing in case Nosto is not connected
* Improve memory usage during indexing proccess
* Encode HTML characters automatically 
* Save storefront domain when creating or connecting Nosto account
* Display warning in case of mismatching live domain with stored domain
* Include active domain and Nosto account in API calls
* Display Nosto tokens in store configuration

### 3.0.4
* Fix issue populating custom fields from Nosto product tags

### 3.0.3
* Fix error that may happen when order and cart items has no parent product associated

### 3.0.2
* Bump Nosto SDK version to fix the double encoded Oauth redirect URL
* Remove redundant module manager dependency from rating helper

### 3.0.1
* Bump Nosto SDK version to support HTTP 2 

### 3.0.0
**New features**
* Add support for using customer group pricing in Nosto recommendations
* Introduce a cli command for connecting Nosto account via command line
* Support using Yotpo ratings and reviews in Nosto recommendations
* Support using same nosto email widget snippet for multiple Nosto accounts
* Update marketing permission to Nosto in real-time when newsletter subscription is changed
* Support adding multiple products to cart from Nosto recommendations

**Fixes & improvements**
* Improve performance for generating tagging (@hostep)
* Fix the issue with product building when no custom fields are found (@hostep)
* Improve error handling for Nosto dashboard in store admin area
* Code style fixes & refactoring

### 2.11.8
* Fix an issue that could prevent the extension to be installed in Magento 2.3

### 2.11.7
* Fix wrong category translation

### 2.11.6
* Add batching for scheduled indexer
 
### 2.11.5
* Fix bundled product throwing exceptions when option has no selections 

### 2.11.4
* Improve the stock status check for Nosto prouducts and SKUs

### 2.11.3
* Fix check causing all SKU’s to have invisible availability

### 2.11.2
* Ensure that the product is available in given store scope before building Nosto product object

### 2.11.1
* Use IframeTrait for URL building in order to make the possible errors more visible

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
