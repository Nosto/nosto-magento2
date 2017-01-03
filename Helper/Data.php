<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use NostoCryptRandom;

/**
 * NostoHelperData helper used for common tasks, mainly configurations.
 */
class NostoHelperData extends AbstractHelper
{
    /**
     * Path to store config installation ID.
     */
    const XML_PATH_INSTALLATION_ID = 'nosto_tagging/installation/id';

    /**
     * Path to store config product image version setting.
     */
    const XML_PATH_IMAGE_VERSION = 'nosto_tagging/image_options/image_version';

    /**
     * @var string the algorithm to use for hashing visitor id.
     */
    const VISITOR_HASH_ALGO = 'sha256';

    /**
     * @var StoreManagerInterface the store manager.
     */
    protected $storeManager;

    /**
     * @var ModuleListInterface the module listing
     */
    protected $moduleListing;

    /**
     * @var WriterInterface the config writer.
     */
    protected $configWriter;

    /**
     * @var ProductMetadataInterface $productMetaData.
     */
    protected $productMetaData;

    const MODULE_NAME = 'Nosto_Tagging';

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
     * @param string $path
     * @param Store|null $store
     * @return mixed|null
     */
    public function getStoreConfig($path, Store $store = null)
    {
        if (is_null($store)) {
            $store = $this->storeManager->getStore(true);
        }
        return $store->getConfig($path);
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
            $installationId = bin2hex(NostoCryptRandom::getRandomString(32));
            $this->configWriter->save(
                self::XML_PATH_INSTALLATION_ID,
                $installationId
            );
            // todo: clear cache.
        }
        return $installationId;
    }

    /**
     * Return the product image version to include in product tagging.
     *
     * @param \Magento\Store\Model\Store|null $store the store model or null.
     *
     * @return string
     */
    public function getProductImageVersion(Store $store = null)
    {
        return $this->getStoreConfig(self::XML_PATH_IMAGE_VERSION, $store);
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

}
