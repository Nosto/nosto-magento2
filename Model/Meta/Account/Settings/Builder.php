<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Meta\Account\Settings;

use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Nosto\Model\Settings;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Variation as NostoVariationHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private NostoLogger $logger;
    private ManagerInterface $eventManager;
    private NostoHelperCurrency $nostoHelperCurrency;
    private NostoDataHelper $nostoDataHelper;
    private NostoVariationHelper $nostoVariationHelper;

    /**
     * Builder constructor.
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param NostoHelperCurrency $nostoHelperCurrency
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoVariationHelper $nostoVariationHelper
     */
    public function __construct(
        NostoLogger $logger,
        ManagerInterface $eventManager,
        NostoHelperCurrency $nostoHelperCurrency,
        NostoDataHelper $nostoDataHelper,
        NostoVariationHelper $nostoVariationHelper
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoHelperCurrency = $nostoHelperCurrency;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoVariationHelper = $nostoVariationHelper;
    }

    /**
     * @param Store $store
     * @return Settings
     */
    public function build(Store $store)
    {
        $settings = new Settings();

        try {
            $settings->setTitle($this->buildTitle($store));
            $settings->setFrontPageUrl($this->buildURL($store));
            $settings->setCurrencyCode($this->nostoHelperCurrency->getTaggingCurrency($store)->getCode());
            $settings->setLanguageCode(substr($store->getConfig('general/locale/code'), 0, 2));
            $settings->setUseCurrencyExchangeRates($this->nostoHelperCurrency->exchangeRatesInUse($store));
            if ($this->nostoHelperCurrency->exchangeRatesInUse($store)) {
                $settings->setDefaultVariantId($this->nostoHelperCurrency->getTaggingCurrency($store)->getCode());
            } elseif ($this->nostoDataHelper->isPricingVariationEnabled($store)) {
                $settings->setDefaultVariantId($this->nostoVariationHelper->getDefaultVariationCode());
            }
            // Currencies are build in CurrenciesCron to avoid increased loading times when logging into the admin
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_settings_load_after', ['settings' => $settings]);

        return $settings;
    }

    /**
     * Helper method to correctly build the store's front page URL by using the store's base URL and
     * explicitly adding the ___store parameter
     *
     * @param Store $store the store for which to build the front-page URL
     * @return string the absolute front-page URL of the store
     */
    private function buildURL(Store $store)
    {
        $url = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        if ($this->nostoDataHelper->getStoreCodeToUrl($store)) {
            $url = HttpRequest::replaceQueryParamInUrl(
                '___store',
                $store->getCode(),
                $url
            );
        }

        return $url;
    }

    /**
     * Helper method to correctly build the store's name for readability by concatenating the name
     * of the website, group and store
     *
     * @param Store $store the store for which to build the common name
     * @return string the complete common name of the store
     * @throws NoSuchEntityException
     */
    private function buildTitle(Store $store): string
    {
        return implode(
            ' - ',
            [$store->getWebsite()->getName(), $store->getGroup()->getName(), $store->getName()]
        );
    }
}
