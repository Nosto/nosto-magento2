<?php

namespace Nosto\Tagging\Model\Meta\Account\Iframe;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Url;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @param Factory           $factory
     * @param Url               $urlHelper
     * @param Data              $dataHelper
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface   $logger
     */
    public function __construct(
        Factory $factory,
        Url $urlHelper,
        Data $dataHelper,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->_factory = $factory;
        $this->_urlHelper = $urlHelper;
        $this->_dataHelper = $dataHelper;
        $this->_localeResolver = $localeResolver;
        $this->_logger = $logger;
    }

    /**
     * @param Store $store
     * @return \Nosto\Tagging\Model\Meta\Account\Iframe
     */
    public function build(Store $store)
    {
        $metaData = $this->_factory->create();

        try {
            $metaData->setUniqueId($this->_dataHelper->getInstallationId());

            $lang = substr($this->_localeResolver->getLocale(), 0, 2);
            $metaData->setLanguage(new \NostoLanguageCode($lang));
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setShopLanguage(new \NostoLanguageCode($lang));

            $metaData->setShopName($store->getName());

            $metaData->setPreviewUrlProduct($this->_urlHelper->getPreviewUrlProduct($store));
            $metaData->setPreviewUrlCategory($this->_urlHelper->getPreviewUrlCategory($store));
            $metaData->setPreviewUrlSearch($this->_urlHelper->getPreviewUrlSearch($store));
            $metaData->setPreviewUrlCart($this->_urlHelper->getPreviewUrlCart($store));
            $metaData->setPreviewUrlFront($this->_urlHelper->getPreviewUrlFront($store));
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
