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

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use NostoIframe;
use Psr\Log\LoggerInterface;

class Builder
{
    private $nostoHelperUrl;
    private $nostoHelperData;
    private $localeResolver;
    private $backendAuthSession;
    private $logger;

    /**
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoHelperData $nostoHelperData
     * @param Session $backendAuthSession
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface $logger
     * @internal param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     */
    public function __construct(
        NostoHelperUrl $nostoHelperUrl,
        NostoHelperData $nostoHelperData,
        Session $backendAuthSession,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoHelperData = $nostoHelperData;
        $this->backendAuthSession = $backendAuthSession;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
    }

    /**
     * @param StoreInterface $store
     * @return NostoIframe
     */
    public function build(StoreInterface $store)
    {
        $metaData = new NostoIframe();

        try {
            $metaData->setUniqueId($this->nostoHelperData->getInstallationId());

            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setLanguageIsoCode($lang);
            /** @noinspection PhpUndefinedMethodInspection */
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguageIsoCodeShop($lang);

            $metaData->setEmail($this->backendAuthSession->getUser()->getEmail());
            $metaData->setPlatform('magento');
            $metaData->setShopName($store->getName());
            $metaData->setUniqueId($this->nostoHelperData->getInstallationId());
            $metaData->setVersionPlatform($this->nostoHelperData->getPlatformVersion());
            $metaData->setVersionModule($this->nostoHelperData->getModuleVersion());
            $metaData->setPreviewUrlProduct($this->nostoHelperUrl->getPreviewUrlProduct($store));
            $metaData->setPreviewUrlCategory($this->nostoHelperUrl->getPreviewUrlCategory($store));
            $metaData->setPreviewUrlSearch($this->nostoHelperUrl->getPreviewUrlSearch($store));
            $metaData->setPreviewUrlCart($this->nostoHelperUrl->getPreviewUrlCart($store));
            $metaData->setPreviewUrlFront($this->nostoHelperUrl->getPreviewUrlFront($store));
        } catch (\NostoException $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
