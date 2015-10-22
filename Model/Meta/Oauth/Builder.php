<?php

namespace Nosto\Tagging\Model\Meta\Oauth;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @param Factory           $factory
     * @param ResolverInterface $localeResolver
     * @param Url      $urlBuilder
     * @param LoggerInterface   $logger
     */
    public function __construct(
        Factory $factory,
        ResolverInterface $localeResolver,
        Url $urlBuilder,
        LoggerInterface $logger
    ) {
        $this->_factory = $factory;
        $this->_localeResolver = $localeResolver;
        $this->_urlBuilder = $urlBuilder;
        $this->_logger = $logger;
    }

    /**
     * @param Store         $store
     * @param \NostoAccount $account
     * @return \Nosto\Tagging\Model\Meta\Oauth
     */
    public function build(Store $store, \NostoAccount $account = null)
    {
        $metaData = $this->_factory->create();

        try {
            $metaData->setScopes(\NostoApiToken::getApiTokenNames());
            $redirectUrl = $this->_urlBuilder->getUrl(
                'nosto/oauth',
                [
                    '_nosid' => true,
                    '_scope_to_url' => true,
                    '_scope' => $store->getCode(),
                ]
            );
            $metaData->setRedirectUrl($redirectUrl);
            $lang = substr($this->_localeResolver->getLocale(), 0, 2);
            $metaData->setLanguage(new \NostoLanguageCode($lang));
            if (!is_null($account)) {
                $metaData->setAccount($account);
            }
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
