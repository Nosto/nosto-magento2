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

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
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
     * Path to the configuration object that stores the preference for real time product updates
     */
    const XML_PATH_PRODUCT_UPDATES = 'nosto/flags/product_updates';

    /**
     * Path to the configuration object that stores the preference for low stock tagging
     */
    const XML_PATH_LOW_STOCK_INDICATION = 'nosto/flags/low_stock_indication';

    /**
     * Path to the configuration object for customized tags
     */
    const XML_PATH_TAG = 'nosto/attributes/';

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

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param NostoHelperScope $nostoHelperScope
     * @param ModuleListInterface $moduleListing
     * @param WriterInterface $configWriter
     * @param ProductMetadataInterface $productMetadataInterface
     */
    public function __construct(
        Context $context,
        NostoHelperScope $nostoHelperScope,
        ModuleListInterface $moduleListing,
        WriterInterface $configWriter,
        ProductMetadataInterface $productMetadataInterface
    ) {
        parent::__construct($context);

        $this->moduleListing = $moduleListing;
        $this->configWriter = $configWriter;
        $this->productMetaData = $productMetadataInterface;
        $this->nostoHelperScope = $nostoHelperScope;
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
     * @param StoreInterface $store the store model or null.
     * @return string the configuration value
     */
    public function getProductImageVersion(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_IMAGE_VERSION, $store);
    }

    /**
     * Returns the value of the selected brand attribute from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return string the configuration value
     */
    public function getBrandAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_BRAND_ATTRIBUTE, $store);
    }

    /**
     * Returns the value of the selected margin attribute from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return string the configuration value
     */
    public function getMarginAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_MARGIN_ATTRIBUTE, $store);
    }

    /**
     * Returns the value of the selected GTIN attribute from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return string the configuration value
     */
    public function getGtinAttribute(StoreInterface $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_GTIN_ATTRIBUTE, $store);
    }

    /**
     * Returns if variation data tagging is enabled from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isVariationTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_VARIATION_TAGGING, $store);
    }

    /**
     * Returns if alt. image data tagging is enabled from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isAltimgTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_ALTIMG_TAGGING, $store);
    }

    /**
     * Returns if rating and review data tagging is enabled from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isRatingTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_RATING_TAGGING, $store);
    }

    /**
     * Returns if inventory data tagging is enabled from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isInventoryTaggingEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_INVENTORY_TAGGING, $store);
    }

    /**
     * Returns if real time product updates are enabled from the configuration table
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isProductUpdatesEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_PRODUCT_UPDATES, $store);
    }

    /**
     * Returns if low stock indication should be tagged
     *
     * @param StoreInterface $store the store model or null.
     * @return bool the configuration value
     */
    public function isLowStockIndicationEnabled(StoreInterface $store = null)
    {
        return (bool)$this->getStoreConfig(self::XML_PATH_LOW_STOCK_INDICATION, $store);
    }

    /**
     * @param string $path
     * @param StoreInterface|Store $store
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
     * Returns the module version number of the currently installed module.
     *
     * @return string the module's version
     */
    public function getModuleVersion()
    {
        $nostoModule = $this->moduleListing->getOne('Nosto_Tagging');
        if (!empty($nostoModule['setup_version'])) {
            return $nostoModule['setup_version'];
        } else {
            return 'unknown';
        }
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
     * @param StoreInterface $store the store model or null.
     * @return null|array of attributes
     */
    public function getTagAttributes($tagId, StoreInterface $store = null)
    {
        $attributesConfig = $this->getStoreConfig(self::XML_PATH_TAG . $tagId, $store);
        if ($attributesConfig == null) {
            return null;
        }

        return explode(',', $attributesConfig);
    }
}
