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
use Magento\Store\Model\StoreManagerInterface;
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
     * @var string the algorithm to use for hashing visitor id.
     */
    const VISITOR_HASH_ALGO = 'sha256';
    const MODULE_NAME = 'Nosto_Tagging';
    private $storeManager;
    private $moduleListing;
    private $configWriter;
    private $productMetaData;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param StoreManagerInterface $storeManager the store manager.
     * @param ModuleListInterface $moduleListing
     * @param WriterInterface $configWriter
     * @param ProductMetadataInterface $productMetadataInterface
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleListing,
        WriterInterface $configWriter,
        ProductMetadataInterface $productMetadataInterface
    ) {
        parent::__construct($context);

        $this->storeManager = $storeManager;
        $this->moduleListing = $moduleListing;
        $this->configWriter = $configWriter;
        $this->productMetaData = $productMetadataInterface;
    }

    /**
     * Return the checksum for string
     *
     * @param string $string
     *
     * @return string
     */
    public static function generateVisitorChecksum($string)
    {
        return hash(self::VISITOR_HASH_ALGO, $string);
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
     * @param string $path
     * @param StoreInterface|Store $store
     * @return mixed|null
     */
    public function getStoreConfig($path, StoreInterface $store = null)
    {
        if ($store === null) {
            $store = $this->storeManager->getStore(true);
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
}
