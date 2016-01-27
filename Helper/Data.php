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
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Data helper used for common tasks, mainly configurations.
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
    const XML_PATH_IMAGE_VERSION = 'nosto_tagging/image_options/image_version';

    /**
     * Path to store config multi currency method setting.
     */
    const XML_PATH_MULTI_CURRENCY_METHOD = 'nosto_tagging/multi_currency/method';

    /**
     * Path to store config scheduled currency exchange rate update enabled setting.
     */
    const XML_PATH_SCHEDULED_CURRENCY_EXCHANGE_RATE_UPDATE_ENABLED = 'nosto_tagging/scheduled_currency_exchange_rate_update/enabled';

    /**
     * Multi currency method option for currency exchange rates.
     */
    const MULTI_CURRENCY_METHOD_EXCHANGE_RATE = 'exchangeRate';

    /**
     * Multi currency method option for price variations in tagging.
     */
    const MULTI_CURRENCY_METHOD_PRICE_VARIATION = 'priceVariation';

    /**
     * @var StoreManagerInterface the store manager.
     */
    protected $_storeManager;

    /**
     * @var WriterInterface the config writer.
     */
    protected $_configWriter;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param StoreManagerInterface $storeManager the store manager.
     * @param WriterInterface $configWriter the config writer.
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter
    )
    {
        parent::__construct($context);

        $this->_storeManager = $storeManager;
        $this->_configWriter = $configWriter;
    }

    /**
     * @param string $path
     * @param Store|null $store
     * @return mixed|null
     */
    public function getStoreConfig($path, Store $store = null)
    {
        if (is_null($store)) {
            $store = $this->_storeManager->getStore(true);
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
            $installationId = bin2hex(\phpseclib_Crypt_Random::string(32));
            $this->_configWriter->save(
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
     * Return the multi currency method in use, i.e. "exchangeRate" or
     * "priceVariation".
     *
     * If "exchangeRate", it means that the product prices in the recommendation
     * is updated through the Exchange Rate API to Nosto.
     *
     * If "priceVariation", it means that the product price variations should be
     * tagged along side the product.
     *
     * @param Store|null $store the store model or null.
     *
     * @return string
     */
    public function getMultiCurrencyMethod(Store $store = null)
    {
        return $this->getStoreConfig(
            self::XML_PATH_MULTI_CURRENCY_METHOD,
            $store
        );
    }

    /**
     * Checks if the multi currency method in use is the "exchangeRate", i.e.
     * the product prices in the recommendation is updated through the Exchange
     * Rate API to Nosto.
     *
     * @param Store|null $store the store model or null.
     *
     * @return bool
     */
    public function isMultiCurrencyMethodExchangeRate(Store $store = null)
    {
        $method = $this->getMultiCurrencyMethod($store);
        return ($method === self::MULTI_CURRENCY_METHOD_EXCHANGE_RATE);
    }

    /**
     * Checks if the multi currency method in use is the "priceVariation", i.e.
     * the product price variations should be tagged along side the product.
     *
     * @param Store|null $store the store model or null.
     *
     * @return bool
     */
    public function isMultiCurrencyMethodPriceVariation(Store $store = null)
    {
        $method = $this->getMultiCurrencyMethod($store);
        return ($method === self::MULTI_CURRENCY_METHOD_PRICE_VARIATION);
    }

    /**
     * Returns if the scheduled currency exchange rate update is enabled.
     *
     * @param Store|null $store the store model or null.
     *
     * @return bool
     */
    public function isScheduledCurrencyExchangeRateUpdateEnabled(
        Store $store = null
    )
    {
        return (bool)$this->getStoreConfig(
            self::XML_PATH_SCHEDULED_CURRENCY_EXCHANGE_RATE_UPDATE_ENABLED,
            $store
        );
    }
}
