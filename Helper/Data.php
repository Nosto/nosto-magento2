<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use phpseclib\Crypt\Random;

/**
 * NostoHelperData helper used for common tasks, mainly configurations.
 */
class Data extends AbstractHelper
{
    /**
     * Path to store config installation ID.
     */
    const XML_PATH_INSTALLATION_ID = 'nosto_tagging/installation/id';

    /**
     * Path to store config product image version setting.
     */
    const XML_PATH_IMAGE_VERSION = 'nosto/images/version';

    /**
     * Path to store config for removing "pub/" directory from image URLs
     */
    const XML_PATH_IMAGE_REMOVE_PUB_FROM_URL = 'nosto/images/remove_pub_directory';

    /**
     * Path to the configuration object that store's the brand attribute
     */
    const XML_PATH_BRAND_ATTRIBUTE = 'nosto/optional/brand';

    /**
     * Path to the configuration object that store's the margin attribute
     */
    const XML_PATH_MARGIN_ATTRIBUTE = 'nosto/optional/margin';

    /**
     * Path to the configuration object that store's the GTIN attribute
     */
    const XML_PATH_GTIN_ATTRIBUTE = 'nosto/optional/gtin';

    /**
     * Path to the configuration object that stores the preference to tag variation data
     */
    const XML_PATH_VARIATION_TAGGING = 'nosto/flags/variation_tagging';

    /**
     * Path to store config for custom fields
     */
    const XML_PATH_USE_CUSTOM_FIELDS = 'nosto/flags/use_custom_fields';

    /**
     * Path to the configuration object that stores the preference to tag alt. image data
     */
    const XML_PATH_ALTIMG_TAGGING = 'nosto/flags/altimg_tagging';

    /**
     * Path to the configuration object that stores the preference to tag rating and review data
     */
    const XML_PATH_RATING_TAGGING = 'nosto/flags/rating_tagging';

    /**
     * Path to the configuration object that stores the preference to tag inventory data
     */
    const XML_PATH_INVENTORY_TAGGING = 'nosto/flags/inventory_tagging';

    /**
     * Path to the configuration object that stores the preference to full reindex
     */
    const XML_PATH_FULL_REINDEX = 'nosto/flags/full_reindex';

    /**
     * Path to the configuration object that stores the preference for real time product updates
     */
    const XML_PATH_PRODUCT_UPDATES = 'nosto/flags/product_updates';

    /**
     * Path to store config for send add to cart event to nosto
     */
    const XML_PATH_SEND_ADD_TO_CART_EVENT = 'nosto/flags/send_add_to_cart_event';

    /**
     * Path to store config for sending customer data to Nosto or not
     */
    const XML_PATH_SEND_CUSTOMER_DATA = 'nosto/flags/send_customer_data';

    /**
     * Path to the configuration object that stores the preference for low stock tagging
     */
    const XML_PATH_LOW_STOCK_INDICATION = 'nosto/flags/low_stock_indication';

    /**
     * Path to the configuration object that stores the preference for adding store code to URL
     */
    const XML_PATH_STORE_CODE_TO_URL = 'nosto/url/store_code_to_url';

    /**
     * Path to the configuration object for customized tags
     */
    const XML_PATH_TAG = 'nosto/attributes/';

    /**
     * Path to the configuration object for multi currency
     */
    const XML_PATH_MULTI_CURRENCY = 'nosto/multicurrency/method';

    /**
     * Values of the multi currency settings
     */
    const SETTING_VALUE_MC_EXCHANGE_RATE = 'exchangerates';
    const SETTING_VALUE_MC_SINGLE = 'single';
    const SETTING_VALUE_MC_DISABLED = 'disabled';
    const SETTING_VALUE_MC_UNDEFINED = 'undefined';

    /**
     * Name of the module
     */
    const MODULE_NAME = 'Nosto_Tagging';

    /**
     * Name of the platform
     */
    const PLATFORM_NAME = 'Magento';

    private $moduleListing;
    private $configWriter;
    private $productMetaData;
    private $nostoHelperScope;
    private $cacheManager;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param NostoHelperScope $nostoHelperScope
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
     * Returns a unique ID that identifies this Magento installation.
     * This ID is sent to the Nosto account config iframe and used to link all
     * Nosto accounts used on this installation.
     *
     * @return string the ID.
     */
    public function getInstallationId()
    {
        $installationId = $this->scopeConfig->getValue(
            self::XML_PATH_INSTALLATION_ID
        );
        if (empty($installationId)) {
            // Running bin2hex() will make the ID string length 64 characters.
            $installationId = bin2hex(Random::string(32));
            $this->configWriter->save(
                self::XML_PATH_INSTALLATION_ID,
                $installationId
            );
        }
        return $installationId;
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
        return (bool)$this->getStoreConfig(self::XML_PATH_RATING_TAGGING, $store);
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
     * Returns true if full reindex is enable
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isFullReindexEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_FULL_REINDEX, $store);
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
     * Returns if real time cart updates are enabled from the configuration table
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isSendAddToCartEventEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_SEND_ADD_TO_CART_EVENT, $store);
    }

    /**
     * Returns if customer data should be send to Nosto
     *
     * @param StoreInterface|null $store the store model or null.
     * @return bool the configuration value
     */
    public function isSendCustomerDataToNostoEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_SEND_CUSTOMER_DATA, $store);
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
     * @suppress PhanUndeclaredConstant
     */
    public function getPlatformVersion()
    {
        $version = 'unknown';
        if ($this->productMetaData->getVersion()) {
            $version = $this->productMetaData->getVersion();
        } /** @noinspection PhpUndefinedClassConstantInspection */ elseif (defined(AppInterface::VERSION)) {
            /** @noinspection PhpUndefinedClassConstantInspection */
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
