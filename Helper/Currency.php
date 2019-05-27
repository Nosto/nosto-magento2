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

namespace Nosto\Tagging\Helper;

use Magento\Directory\Model\Currency as MagentoCurrency;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as NostoHelperData;

/**
 * Currency helper used for currency related tasks.
 */
class Currency extends AbstractHelper
{
    private $nostoHelperData;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        Context $context,
        NostoHelperData $nostoHelperData
    ) {
        parent::__construct($context);

        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * If the store uses multiple currencies the prices are converted from base
     * currency into given currency. Otherwise the given price is returned.
     *
     * @param float $basePrice The price of a product in base currency
     * @param Store $store
     * @return float
     * @throws \Exception
     */
    public function convertToTaggingPrice($basePrice, Store $store)
    {
        // If multi currency is disabled or exchange rates are used
        // we don't do any processing / conversions for the price
        if ($this->nostoHelperData->isMultiCurrencyDisabled($store)
            || $this->nostoHelperData->isMultiCurrencyExchangeRatesEnabled($store)
        ) {
            return $basePrice;
        }

        $taggingPrice = $basePrice;
        $taggingCurrency = $this->getTaggingCurrency($store);
        $baseCurrency = $store->getBaseCurrency();

        if ($taggingCurrency->getCurrencyCode() !== $baseCurrency->getCurrencyCode()) {
            $taggingPrice = $baseCurrency->convert($basePrice, $taggingCurrency);
        }

        return $taggingPrice;
    }

    /**
     * Returns the currency that must be used in tagging
     *
     * @param Store $store
     * @return MagentoCurrency
     */
    public function getTaggingCurrency(Store $store)
    {
        // If multi currency is disabled or exhange rates are used
        // we always use the base currency for tagging
        if ($this->nostoHelperData->isMultiCurrencyExchangeRatesEnabled($store)
            || $this->nostoHelperData->isMultiCurrencyDisabled($store)
        ) {
            $taggingCurrency = $store->getBaseCurrency();
        } else {
            $taggingCurrency = $store->getDefaultCurrency();
        }

        return $taggingCurrency;
    }

    /**
     * Returns the amount of currencies used in given store
     *
     * @param Store $store
     * @return int
     */
    public function getCurrencyCount(Store $store)
    {
        $currencies = $store->getAvailableCurrencyCodes(true);

        return count($currencies);
    }

    /**
     * Returns the info if exchange rates are used
     *
     * @param Store $store
     * @return boolean
     */
    public function exchangeRatesInUse(Store $store)
    {
        if ($this->nostoHelperData->isMultiCurrencyExchangeRatesEnabled($store)) {
            return true;
        }
        $method = $this->nostoHelperData->getMultiCurrencyMethod($store);
        // Determine the value for MC setting if it's undefined
        if ($method === Data::SETTING_VALUE_MC_UNDEFINED) {
            if ($this->getCurrencyCount($store) > 1) {
                $this->nostoHelperData->saveMultiCurrencyMethod(Data::SETTING_VALUE_MC_EXCHANGE_RATE, $store);
                $this->nostoHelperData->clearMagentoCache('config');
                return true;
            }
            if ($this->getCurrencyCount($store) === 1) {
                $this->nostoHelperData->saveMultiCurrencyMethod(Data::SETTING_VALUE_MC_SINGLE, $store);
                $this->nostoHelperData->clearMagentoCache('config');
            }
        }
        return false;
    }
}
