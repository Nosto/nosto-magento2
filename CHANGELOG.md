All notable changes to this project will be documented in this file. This project adheres to Semantic Versioning.

### 7.2.5
* Restore `nosto_product_sync.delete` message queue consumer to handle product deletion

### 7.2.4
* Add null check in order observer to allow for orders overriding

### 7.2.3
* Update PHP-SDK to fix an issue that would cause category id's to be removed by the crawler

### 7.2.2
* Fixes an issue with Zend_Validate that would cause the admin to not connect to a Nosto account

### 7.2.1
* Add store filter to the DefaulCategoryService to generate categories for specific store

### 7.2.0 
* Remove `nosto_product_sync.delete` message queue consumer

### 7.1.2
* Remove unreffered system configuration for enabling 'Indexer full reindex' for Nosto indexers

### 7.1.1
* Fix query filter format. Uses `IN` instead of `OR` when filtering attributes (by @TzviCons)

### 7.1.0
* Add category ids to product tagging

### 7.0.0
* Removes queue processor indexer. Now there is a single indexer, which simply sends product ID's to the message queue with an action to either update or delete products that will be consumed by SyncService
* By removing the indexer, fixes an issue with duplicated rows, which caused the indexer to run multiple times for the same product
* By removing the indexer, also fixes an issue with out of memory when trying to index large catalogs
* By removing the indexer, also fixes an issue with MassProductAttributeUpdate operations, which would cause the index queue to be over populated when indexer was on save mode

### 6.1.6
* Fix iteration on API sync service

### 6.1.5
* Remove check for duplicate batch entries in the product update queue table

### 6.1.4
* Remove factories from ProductCollectionBuilder and QueueCollectionBuilder to reduce indexer memory footprint

### 6.1.3
* Generate visitor checksum only if the CID cookie exists

### 6.1.2
* Remove installation id and unique id from Magento module
* Remove false success message after account reconnection
* Add current account info to admin view

### 6.1.1
* Fix recobuy.js from crashing the cart on storefront

### 6.1.0
* Remove Iframe. Nosto account connection is now configured externally and redirects back to the Magento 2 admin
* Remove preview URLs that were used in the iframe.
* Add system notification for missing API tokens 
* Add guard to customer reference when multi-channel order tracking is enable but customer is not logged in

### 6.0.6
* Fix issue with product builder when products have null prices

### 6.0.5
* Fix call to undefined warn logger method

### 6.0.4
* Fix issue where nosto_tagging_customer would get a lot of entries

### 6.0.3
* Indexer - Prevent insertion of duplicated rows to be delete

### 6.0.2
* Indexer - Reduces completed queue cleanup time to 1 hour instead of 8
* Indexer Sync Service - Uses Magento Product repository to fetch product data in bulk, reducing amount of database queries
* Indexer - Prevent insertion of duplicated rows to be upsert
* Move currencies building process to a cronjob, reducing admin loading time in setups with a large amount of currencies and storeviews

### 6.0.1
* Add support for both version 2 and 3 of phpseclib/phpseclib library (for compatibility with Magento versions 2.3.x and 2.4.x)
* Remove customer visitor checksum generation when 2c.cid cookie does not exist

### 6.0.0
* Compatibility with Magento 2.4.4
* Bump minimum PHP version to 7.4
* Use of XML schemas and data patches
* Remove personally identifiable information from the module

### 5.4.3
* Add page-type tagging for checkout page

### 5.4.2
* Send all active currencies formating to Nosto only when multi-currency is enabled
* Fix failing product data tagging for configurable product with no SKUs

### 5.4.1
* Fix product availability building for products with OOS threshold

### 5.4.0
* Add functionality to send disabled products to Nosto
* Add ttl for nosto_product_cache
* Refactor BuilderTrait into service classes

### 5.3.2
* Improve exception during product build by passing previos exception

### 5.3.1
* Fix to use placeholder thumbnail image if product has no image

### 5.3.0
* Index products to Nosto after bulk updates

### 5.2.10
* Improve message text for mixed nosto accounts
* Remove old changelog tables when upgrading to v5

### 5.2.9
* Upgrade sdk version to update phpseclib dependency

### 5.2.8
* Fix simple products not being reindex issue

### 5.2.7
* Add configuration to reload recs after adding product to cart

### 5.2.6
* Get the correct product data by emulating the store

### 5.2.5
* Fix product update consumer running out of memory issue 

### 5.2.4
* Fix order tagging rendering in case variation tags were missing

### 5.2.3
* Pass correct product id when adding grouped product to cart

### 5.2.2
* Fix bug where disabled parent product ids are added to reindex queue

### 5.2.1
* Sort categories inside breadcrumb based on their level

### 5.2.0
* Remove synchronous product indexing
* Improve queue consumer message

### 5.1.2
* Update PHP-SDK dependency version for better http exception logging

### 5.1.1
* Check that order payment is instance of payment interface

### 5.1.0
* Fix addMultipleProductsToCart issue happening in M2 cloud

### 5.0.8
* Bump dependencies to be compatible with Nosto CMP module  

### 5.0.7
* User serializer provided by the PHP SDK to keep the module compatible with Magento EQP  

### 5.0.6
* Add Content Security Policy (CSP) whitelist 

### 5.0.5
* Fix an issue where if deleted user with ID 1, the indexer will throw foreign keys constraints errors

### 5.0.4
* Fix an issue with incorrect prices when different base currencies are used in websites and taxes are included in display prices   

### 5.0.3
* Bump the PHP SDK version to be compatible with Nosto CMP module (no functional changes)

### 5.0.2
* Fix an issue where custom tags (tag1) were overridden by default tags
  
### 5.0.1
* Fix an issue with configurable product prices being zero in Nosto product data when taxes are included in display prices

### 5.0.0
* Refactor the indexing logic to use batched queues & decouple the caching logic from product updates
* Use Magento's built-in caching logic for caching Nosto product data 
* Add google category as customisable attribute
* Change the namespaces to comply with PHP SDK 5.0.0
* Add check for an empty array before trying to get min price for bundled product price
* Remove mview subscription / trigger to catalog_product_entity_media_gallery

### 4.0.9
* Fix an issue with configurable product prices not being set when using MSI  

### 4.0.8
* Add null guard for caching product service in case the product data building fails for dirty product

### 4.0.7
* Handle empty / invalid product cache entries and possible failures in product data building gracefully

### 4.0.6
* Fix issue with non-generated proxy classes during di compilation 

### 4.0.5
* Fix an issue where product cache table was not created during upgrade

### 4.0.4
* Store cached Nosto product data as a base64 encoded string in database to avoid problems with character sets and collations
* Alter the type of cached product to be longtext to allow saving large product data sets

### 4.0.3
* `setup:upgrade` for customer now saves only customer reference instead of entire customer object

### 4.0.2
* Fix an issue where setup:upgrade could crash if customer migration is faulty

### 4.0.1
* Make the new order detection more fault tolerant by comparing also updated at and created at timestamps

### 4.0.0
**New features (performance improvements)**
* Introduce cache for Nosto product data to speedup the product tagging added to the product pages
* Introduce Nosto product data change detection to avoid redundant API calls to Nosto
* Utilize bulk operations for product updates
* Add support for indexing in parallel mode
* Introduce caching for building product attribute values
* Introduce caching layer for building categories

**Bug fixes and improvements**
* Generate customer reference for all registered customers automatically during setup upgrade
* Cleanup the change log database table after indexer run
* Prevent redundant full reindex on Nosto indexers when running `setup:upgrade`
* Fix GTIN attribute being set with margin value 

**Removed features / functionalities**
* Remove logic for sending cart updates to Nosto from server side

### 3.10.5
* Hide Nosto customer reference for registered customers in account edit view

### 3.10.4
* Bump SDK version to 4.0.10 to fix OrderStatus Handlers throwing exceptions

### 3.10.3
* Fix an issue where product url contains the category breadcrumbs if shortest url is not first entry in database table

### 3.10.2
* Fix an issue where the indexer page size was not set properly (Credit goes to `Deepak Upadhyay` (https://github.com/dupadhyay3))

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
