<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Meta\Account;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Signup\Signup;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Model\Meta\Account\Billing\Builder as NostoBillingBuilder;
use Nosto\Tagging\Model\Meta\Account\Settings\Currencies\Builder as NostoCurrenciesBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Helper\Data as NostoDataHelper;

class Builder
{
    const API_TOKEN = 'YBDKYwSqTCzSsU8Bwbg4im2pkHMcgTy9cCX7vevjJwON1UISJIwXOLMM0a8nZY7h';
    const PLATFORM_NAME = 'magento';
    private $accountBillingMetaBuilder;
    private $localeResolver;
    private $logger;
    private $eventManager;
    private $nostoHelperCurrency;
    private $nostoCurrenciesBuilder;
    private $nostoDataHelper;

    /**
     * @param NostoHelperCurrency $nostoHelperCurrency
     * @param NostoBillingBuilder $nostoAccountBillingMetaBuilder
     * @param NostoCurrenciesBuilder $nostoCurrenciesBuilder
     * @param ResolverInterface $localeResolver
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param NostoDataHelper $nostoDataHelper
     */
    public function __construct(
        NostoHelperCurrency $nostoHelperCurrency,
        NostoBillingBuilder $nostoAccountBillingMetaBuilder,
        NostoCurrenciesBuilder $nostoCurrenciesBuilder,
        ResolverInterface $localeResolver,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        NostoDataHelper $nostoDataHelper
    ) {
        $this->accountBillingMetaBuilder = $nostoAccountBillingMetaBuilder;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoHelperCurrency = $nostoHelperCurrency;
        $this->nostoCurrenciesBuilder = $nostoCurrenciesBuilder;
        $this->nostoDataHelper = $nostoDataHelper;
    }

    /**
     * @param Store $store
     * @param $accountOwner
     * @param $signupDetails
     * @return Signup
     */
    public function build(Store $store, $accountOwner, $signupDetails)
    {
        $metaData = new Signup(self::PLATFORM_NAME, self::API_TOKEN, null);

        try {
            $metaData->setTitle(
                implode(
                    ' - ',
                    [
                        $store->getWebsite()->getName(),
                        $store->getGroup()->getName(),
                        $store->getName()
                    ]
                )
            );
            $metaData->setName(substr(sha1((string)rand()), 0, 8));
            $url = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            if ($this->nostoDataHelper->getStoreCodeToUrl($store)) {
                $url = HttpRequest::replaceQueryParamInUrl(
                    '___store',
                    $store->getCode(),
                    $url
                );
            }
            $metaData->setFrontPageUrl($url);
            $metaData->setCurrencies($this->nostoCurrenciesBuilder->build($store));
            $metaData->setCurrencyCode($this->nostoHelperCurrency->getTaggingCurrency($store)->getCode());
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguageCode($lang);
            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setOwnerLanguageCode($lang);
            $metaData->setOwner($accountOwner);
            if ($this->nostoHelperCurrency->exchangeRatesInUse($store)) {
                $metaData->setDefaultVariantId(
                    $this->nostoHelperCurrency->getTaggingCurrency($store)
                        ->getCode()
                );
            }

            $billing = $this->accountBillingMetaBuilder->build($store);
            $metaData->setBillingDetails($billing);

            $metaData->setDetails($signupDetails);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_account_load_after', ['account' => $metaData]);

        return $metaData;
    }
}
