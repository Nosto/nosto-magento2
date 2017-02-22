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

namespace Nosto\Tagging\Model\Meta\Account\Iframe;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Nosto\Sdk\NostoIframe;
use Nosto\Sdk\NostoLanguageCode;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Url;
use Psr\Log\LoggerInterface;

class Builder
{
    private $_urlHelper;
    private $_dataHelper;
    private $_localeResolver;
    private $_logger;

    /**
     * @param Url $urlHelper
     * @param Data $dataHelper
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Url $urlHelper,
        Data $dataHelper,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->_urlHelper = $urlHelper;
        $this->_dataHelper = $dataHelper;
        $this->_localeResolver = $localeResolver;
        $this->_logger = $logger;
    }

    /**
     * @param Store $store
     * @return NostoIframe
     */
    public function build(Store $store)
    {
        $metaData = new NostoIframe();

        try {
            $metaData->setUniqueId($this->_dataHelper->getInstallationId());

            $lang = substr($this->_localeResolver->getLocale(), 0, 2);
            $metaData->setLanguage(new NostoLanguageCode($lang));
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setShopLanguage(new NostoLanguageCode($lang));

            $metaData->setShopName($store->getName());
            $metaData->setUniqueId($this->_dataHelper->getInstallationId());
            $metaData->setVersionPlatform($this->_dataHelper->getPlatformVersion());
            $metaData->setVersionModule($this->_dataHelper->getModuleVersion());
            $metaData->setPreviewUrlProduct($this->_urlHelper->getPreviewUrlProduct($store));
            $metaData->setPreviewUrlCategory($this->_urlHelper->getPreviewUrlCategory($store));
            $metaData->setPreviewUrlSearch($this->_urlHelper->getPreviewUrlSearch($store));
            $metaData->setPreviewUrlCart($this->_urlHelper->getPreviewUrlCart($store));
            $metaData->setPreviewUrlFront($this->_urlHelper->getPreviewUrlFront($store));
        } catch (\Nosto\Sdk\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
