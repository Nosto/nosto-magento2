<?php /** @noinspection ALL */
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

/**
 * NostoHelperData helper used for common tasks, mainly configurations.
 */
class Data extends AbstractHelper
{
    /**
     * Path to store config product image version setting.
     */
    public const XML_PATH_IMAGE_VERSION = 'nosto/images/version';

    /**
     * Path to store config for removing "pub/" directory from image URLs
     */
    public const XML_PATH_IMAGE_REMOVE_PUB_FROM_URL = 'nosto/images/remove_pub_directory';

    /**
     * Path to the configuration object that store's the brand attribute
     */
    public const XML_PATH_BRAND_ATTRIBUTE = 'nosto/optional/brand';

    /**
     * Path to the configuration object that store's the margin attribute
     */
    public const XML_PATH_MARGIN_ATTRIBUTE = 'nosto/optional/margin';

    /**
     * Path to the configuration object that store's the GTIN attribute
     */
    public const XML_PATH_GTIN_ATTRIBUTE = 'nosto/optional/gtin';

    /**
     * Path to the configuration object that store's the google_category attribute
     */
    public const XML_PATH_GOOGLE_CATEGORY_ATTRIBUTE = 'nosto/optional/google_category';

    /**
     * Path to the configuration object that stores the preference to tag variation data
     */
    public const XML_PATH_VARIATION_TAGGING = 'nosto/flags/variation_tagging';

    /**
     * Path to store config for custom fields
     */
    public const XML_PATH_USE_CUSTOM_FIELDS = 'nosto/flags/use_custom_fields';

    /**
     * Path to the configuration object that stores the preference to tag alt. image data
     */
    public const XML_PATH_ALTIMG_TAGGING = 'nosto/flags/altimg_tagging';

    /**
     * Path to the configuration object that stores the preference to tag rating and review data
     */
    public const XML_PATH_RATING_TAGGING = 'nosto/flags/rating_tagging';

    /**
     * Path to the configuration object that stores the preference to tag inventory data
     */
    public const XML_PATH_INVENTORY_TAGGING = 'nosto/flags/inventory_tagging';

    /**
     * Path to the configuration object that stores the preference for real time product updates
     */
    public const XML_PATH_PRODUCT_UPDATES = 'nosto/flags/product_updates';

    /**
     * Path to store config for sending customer data to Nosto or not
     */
    public const XML_PATH_SEND_CUSTOMER_DATA = 'nosto/flags/send_customer_data';

    /**
     * Path to the configuration object that stores the preference for low stock tagging
     */
    public const XML_PATH_LOW_STOCK_INDICATION = 'nosto/flags/low_stock_indication';

    /**
     * Path to the configuration object that stores the percentage of PHP available memory for indexer
     */
    public const XML_PATH_INDEXER_MEMORY = 'nosto/flags/indexer_memory';

    /**
     * Product per request
     */
    public const XML_PATH_PRODUCT_PER_REQUEST = 'nosto/flags/product_per_request';

    /**
     * Request timeout
     */
    public const XML_PATH_REQUEST_TIMEOUT = 'nosto/flags/request_timeout';

    /**
     * Path to the configuration object that stores the preference for indexing disabled products
     */
    public const XML_PATH_INDEX_DISABLED_PRODUCTS = 'nosto/flags/indexer_disabled_products';

    /*
     * Path to the configuration object for tagging the date a product has beed added to Magento's catalog
     */
    public const XML_PATH_TAG_DATE_PUBLISHED = 'nosto/flags/tag_date_published';

    /**
     * Path to the configuration object that stores customer reference
     */
    public const XML_PATH_TRACK_MULTI_CHANNEL_ORDERS = 'nosto/flags/track_multi_channel_orders';

    /**
     * Path to the configuration object that stores preference for reloading recs after adding product to cart
     */
    public const XML_PATH_RELOAD_RECS_AFTER_ATC = 'nosto/flags/reload_recs_after_atc';

    /**
     * Path to the configuration object for pricing variations
     */
    public const XML_PATH_PRICING_VARIATION = 'nosto/multicurrency/pricing_variation';

    /**
     * Path to the configuration object that stores the preference for adding store code to URL
     */
    public const XML_PATH_STORE_CODE_TO_URL = 'nosto/url/store_code_to_url';

    /**
     * Path to the configuration object for customized tags
     */
    public const XML_PATH_TAG = 'nosto/attributes/';

    /**
     * Path to the configuration object for multi currency
     */
    public const XML_PATH_MULTI_CURRENCY = 'nosto/multicurrency/method';

    /**
     * Path to the configuration object for light indexer
     */
    public const XML_PATH_USE_LIGHT_INDEXER = 'nosto/flags/use_light_indexer';

    /**
     * @var string Nosto customer reference attribute name
     */
    public const NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME = 'nosto_customer_reference';

    /**
     * Values for ratings settings
     */
    public const SETTING_VALUE_YOTPO_RATINGS = '2';
    public const SETTING_VALUE_MAGENTO_RATINGS = '1';
    public const SETTING_VALUE_NO_RATINGS = '0';

    /**
     * Values of the multi currency settings
     */
    public const SETTING_VALUE_MC_EXCHANGE_RATE = 'exchangerates';
    public const SETTING_VALUE_MC_SINGLE = 'single';
    public const SETTING_VALUE_MC_DISABLED = 'disabled';
    public const SETTING_VALUE_MC_UNDEFINED = 'undefined';

    /**
     * Name of the module
     */
    public const MODULE_NAME = 'Nosto_Tagging';

    /**
     * Name of the platform
     */
    public const PLATFORM_NAME = 'Magento';

    private ModuleListInterface $moduleListing;
    private WriterInterface $configWriter;
    private ProductMetadataInterface $productMetaData;
    private Scope $nostoHelperScope;
    private CacheManager $cacheManager;

    /**
     * Data constructor.
     * @param Context $context
     * @param Scope $nostoHelperScope
     * @param ModuleListInterface $moduleListing
     * @param WriterInterface $configWriter
     * @param ProductMetadataInterface $productMetadataInterface
     * @param CacheManager $cacheManager
     */
    public function __construct(
        Context $context,
        NostoHelperScope $nostoHelperScope,
        ModuleListInterface $moduleListing,
        WriterInterface $configWriter,
        ProductMetadataInterface $productMetadataInterface,
        CacheManager $cacheManager
    ) {
        parent::__construct($context);

        $this->moduleListing = $moduleListing;
        $this->configWriter = $configWriter;
        $this->productMetaData = $productMetadataInterface;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Returns the value of the selected image version option from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getProductImageVersion(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_IMAGE_VERSION, $store);
    }

    /**
     * Returns boolean if "pub/" directory should be removed from product image
     * URLs. This is needed because M2 CLI doesn't know if the docroot is pointing to
     * "pub/" directory or Magento root.
     *
     * @param StoreInterface|null $store the store model or null.
     * @return boolean
     */
    public function getRemovePubDirectoryFromProductImageUrl(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_IMAGE_REMOVE_PUB_FROM_URL, $store);
    }

    /**
     * Returns the value of the selected brand attribute from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getBrandAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_BRAND_ATTRIBUTE, $store);
    }

    /**
     * Returns the value of the selected margin attribute from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getMarginAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_MARGIN_ATTRIBUTE, $store);
    }

    /**
     * Returns the value of the selected GTIN attribute from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getGtinAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_GTIN_ATTRIBUTE, $store);
    }

    /**
     * Returns the value of the selected google category attribute from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getGoogleCategoryAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_GOOGLE_CATEGORY_ATTRIBUTE, $store);
    }

    /**
     * Returns if variation data tagging is enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isVariationTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_VARIATION_TAGGING, $store);
    }

    /**
     * Returns on/off setting for custom fields
     *
     * @param StoreInterface|null $store the store model or null.
     * @return boolean
     */
    public function isCustomFieldsEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_USE_CUSTOM_FIELDS, $store);
    }

    /**
     * Returns if alt. image data tagging is enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isAltimgTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_ALTIMG_TAGGING, $store);
    }

    /**
     * Returns if rating and review data tagging is enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isRatingTaggingEnabled(StoreInterface $store = null)
    {
        $providerCode = $this->getStoreConfig(self::XML_PATH_RATING_TAGGING, $store);

        if ((int)$providerCode === 0) {
            return false;
        }

        return true;
    }

    /**
     * Returns the provider used for ratings and reviews
     *
     * @param StoreInterface|null $store
     * @return mixed|null
     */
    public function getRatingTaggingProvider(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_RATING_TAGGING, $store);
    }

    /**
     * Returns if inventory data tagging is enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isInventoryTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_INVENTORY_TAGGING, $store);
    }

    /**
     * Returns if real time product updates are enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isProductUpdatesEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_PRODUCT_UPDATES, $store);
    }

    /**
     * Returns if customer data should be sent to Nosto
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isSendCustomerDataToNostoEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_SEND_CUSTOMER_DATA, $store);
    }

    /**
     * Returns if orders want to be tracked from various channels
     *
     * @param StoreInterface|null $store
     * @return bool
     */
    public function isMultiChannelOrderTrackingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_TRACK_MULTI_CHANNEL_ORDERS, $store);
    }

    /**
     * Returns if recs should be reloaded after adding product to cart
     *
     * @param StoreInterface|null $store
     * @return int
     */
    public function isReloadRecsAfterAtcEnabled(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_RELOAD_RECS_AFTER_ATC, $store);
    }

    /**
     * Returns if low stock indication should be tagged
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isLowStockIndicationEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_LOW_STOCK_INDICATION, $store);
    }

    /**
     * Returns maximum percentage of PHP available memory that indexer should use
     *
     * @param StoreInterface|null $store the store model or null.
     * @return int the configuration value
     */
    public function getIndexerMemory(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_INDEXER_MEMORY, $store);
    }

    /**
     * Products per request
     *
     * @param StoreInterface|null $store the store model or null.
     * @return int the configuration value
     */
    public function getProductsPerRequest(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_PRODUCT_PER_REQUEST, $store);
    }

    /**
     * Request timeout
     *
     * @param StoreInterface|null $store the store model or null.
     * @return int the configuration value
     */
    public function getRequestTimeout(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_REQUEST_TIMEOUT, $store);
    }

    /**
     * Returns maximum percentage of PHP available memory that indexer should use
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function canIndexDisabledProducts(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_INDEX_DISABLED_PRODUCTS, $store);
    }

    /**
     * Returns on/off setting for tagging product's date published
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isTagDatePublishedEnabled(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_TAG_DATE_PUBLISHED, $store);
    }

    /**
     * Returns if pricing variation is enabled
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isPricingVariationEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_PRICING_VARIATION, $store);
    }

    /**
     * Returns if multi currency is disabled
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isMultiCurrencyDisabled(StoreInterface $store = null)
    {
        $storeConfig = $this->getMultiCurrencyMethod($store);
        return ($storeConfig === self::SETTING_VALUE_MC_DISABLED);
    }

    /**
     * Returns if multi currency is enabled
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isMultiCurrencyExchangeRatesEnabled(StoreInterface $store = null)
    {
        $storeConfig = $this->getMultiCurrencyMethod($store);
        return ($storeConfig === self::SETTING_VALUE_MC_EXCHANGE_RATE);
    }

    /**
     * Returns the multi currency setup value / multi currency method
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getMultiCurrencyMethod(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_MULTI_CURRENCY, $store);
    }

    /**
     * Returns if the light indexer should be used
     *
     * @param StoreInterface|null $store the store model or null.
     * @return string the configuration value
     */
    public function getUseLightIndexer(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_USE_LIGHT_INDEXER, $store);
    }

    /**
     * Saves the multi currency setup value / multi currency method
     *
     * @param string $value the value of the multi currency setting.
     * @param StoreInterface|null $store the store model or null.
     * @return string|null the configuration value
     */
    public function saveMultiCurrencyMethod($value, StoreInterface $store = null)
    {
        return $this->saveStoreConfig(self::XML_PATH_MULTI_CURRENCY, $value, $store);
    }

    /**
     * @param string $path
     * @param StoreInterface|Store|null $store
     * @return mixed|null
     */
    public function getStoreConfig($path, StoreInterface $store = null)
    {
        if ($store === null) {
            $store = $this->nostoHelperScope->getStore(true);
        }
        return $store->getConfig($path);
    }

    /**
     * @param string $path
     * @param mixed $value
     * @param StoreInterface|Store|null $store
     */
    public function saveStoreConfig($path, $value, StoreInterface $store = null)
    {
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $storeId = 0;
        if ($store !== null) {
            $scope = 'stores'; // No const found for this one in M2.2.2
            $storeId = $store->getStoreId(); // No const found for this one in M2.2.2
        }

        $this->configWriter->save($path, $value, $scope, $storeId);
    }

    /**
     * Returns the module version number of the currently installed module.
     *
     * @return string the module's version
     */
    public function getModuleVersion()
    {
        $nostoModule = $this->moduleListing->getOne('Nosto_Tagging');
        if (!empty($nostoModule['setup_version'])) {
            return $nostoModule['setup_version'];
        }
        return 'unknown';
    }

    /**
     * Returns the version number of the platform the e-commerce installation
     *
     * @return string the platforms's version
     * @suppress PhanUndeclaredConstantOfClass
     * @noinspection PhpUndefinedClassConstantInspection
     */
    public function getPlatformVersion()
    {
        $version = 'unknown';
        if ($this->productMetaData->getVersion()) {
            $version = $this->productMetaData->getVersion();
        } elseif (defined(AppInterface::VERSION)) {
            $version = AppInterface::VERSION;
        }
        return $version;
    }

    /**
     * Returns the edition (community/enterprise) of the platform the e-commerce installation
     *
     * @return string the platforms's edition
     */
    public function getPlatformEdition()
    {
        $edition = 'unknown';
        if ($this->productMetaData->getEdition()) {
            $edition = $this->productMetaData->getEdition();
        }

        return $edition;
    }

    /**
     * Get tag1 mapping attributes
     *
     * @param string $tagId tag1, tag2 or tag3
     * @param StoreInterface|null $store the store model or null.
     * @return null|array of attributes
     */
    public function getTagAttributes($tagId, StoreInterface $store = null)
    {
        $attributesConfig = $this->getStoreConfig(self::XML_PATH_TAG . $tagId, $store);
        /** @noinspection TypeUnsafeComparisonInspection */
        if ($attributesConfig == null) {
            return null;
        }

        return explode(',', $attributesConfig);
    }

    /**
     * Returns the value if store codes should be added to Nosto URLs
     *
     * @param StoreInterface|null $store the store model or null.
     * @return boolean the configuration value
     */
    public function getStoreCodeToUrl(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_STORE_CODE_TO_URL, $store);
    }

    /**
     * Clears Magento cache for given type (config, layout, block_html, etc.)
     * @see http://devdocs.magento.com/guides/v2.2/config-guide/cli/config-cli-subcommands-cache.html
     *
     * @param string $type give "all" to clear all
     */
    public function clearMagentoCache($type)
    {
        $types = $this->cacheManager->getAvailableTypes();
        $clearTypes = [];
        if ($type === 'all') {
            $clearTypes = $types;
        } elseif (in_array($type, $types, false)) {
            $clearTypes[] = $type;
        }
        if (!empty($clearTypes)) {
            $this->cacheManager->clean($clearTypes);
        }
    }
}
