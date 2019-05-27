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

namespace Nosto\Tagging\Model\Meta\Account\Settings\Currencies;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Store\Model\Store;
use Nosto\Object\Format;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private $logger;
    private $eventManager;
    private $currencyFactory;
    private $localeResolver;
    /* List of zero decimal currencies in compliance with ISO-4217 */
    const ZERO_DECIMAL_CURRENCIES = [
        'XOF',
        'BIF',
        'XAF',
        'CLP',
        'KMF',
        'DJF',
        'GNF',
        'ISK',
        'JPY',
        'KRW',
        'PYG',
        'RWF',
        'UGX',
        'UYI',
        'VUV',
        'VND',
        'XPF'
    ];

    /**
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyFactory $currencyFactory
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        NostoLogger $logger,
        ManagerInterface $eventManager,
        CurrencyFactory $currencyFactory,
        LocaleResolver $localeResolver
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->currencyFactory = $currencyFactory;
        $this->localeResolver = $localeResolver;
    }

    /**
     * @param Store $store
     * @return array
     * @suppress PhanTypeArraySuspicious
     */
    public function build(Store $store)
    {
        $currencies = [];
        try {
            $storeLocale = $store->getConfig('general/locale/code');
            $localeCode = $storeLocale ?: $this->localeResolver->getLocale();
            $localeData = (new DataBundle())->get($localeCode);
            $defaultSet = $localeData['NumberElements']['default'] ?: 'latn';

            $priceFormat = $this->getPriceFormat($localeData, $defaultSet);
            $decimalSymbol = $this->buildDecimalSymbol($localeData, $defaultSet);
            $groupSymbol = $this->buildGroupSymbol($localeData, $defaultSet);
            $precision = $this->getDecimalPrecision($priceFormat);

            $currencyCodes = $store->getAvailableCurrencyCodes(true);
            if (is_array($currencyCodes) && !empty($currencyCodes)) {
                foreach ($currencyCodes as $currencyCode) {
                    $finalPrecision = $this->isZeroDecimalCurrency($currencyCode) ? 0 : $precision;
                    $currency = $this->currencyFactory->create()->load($currencyCode); // @codingStandardsIgnoreLine
                    $currencies[$currency->getCode()] = new Format(
                        $this->isSymbolBeforeAmount($localeData, $defaultSet),
                        $currency->getCurrencySymbol(),
                        $decimalSymbol,
                        $groupSymbol,
                        $finalPrecision
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_currencies_load_after', ['currencies' => $currencies]);

        return $currencies;
    }

    /**
     * Returns the decimal precision used by the currency
     *
     * @param $priceFormat
     * @return bool|int
     */
    private function getDecimalPrecision($priceFormat)
    {
        $precision = 0;
        if (($decimalPos = strpos($priceFormat, '.')) !== false) {
            $precision = (strlen($priceFormat) - (strrpos($priceFormat, '.') + 1));
        } else {
            $decimalPos = strlen($priceFormat);
        }
        $decimalFormat = substr($priceFormat, $decimalPos);
        if (($pos = strpos($decimalFormat, '#')) !== false) {
            $precision = strlen($decimalFormat) - $pos - $precision;
        }
        return $precision;
    }

    /**
     * Returns true if currency is defined to have no decimal part
     * according to ISO-4217
     *
     * @param $currencyCode
     * @return bool
     */
    private function isZeroDecimalCurrency($currencyCode)
    {
        return in_array($currencyCode, self::ZERO_DECIMAL_CURRENCIES);
    }
    /**
     * Returns true if symbol position is before the amount, false otherwise.
     *
     * @param $localeData
     * @param $defaultSet
     * @return bool
     */
    private function isSymbolBeforeAmount($localeData, $defaultSet)
    {
        // Check if the currency symbol is before or after the amount.
        $priceFormat = $this->getPriceFormat($localeData, $defaultSet);
        return strpos(trim($priceFormat), '¤') !== 0;
    }

    /**
     * Returns the price format from the locale only with
     * the following characters: ["0", "#", ".", ",",]
     *
     * @param $localeData
     * @param $defaultSet
     * @return null|string|string[]
     */
    private function getPriceFormat($localeData, $defaultSet)
    {
        $priceFormat = $this->buildPriceFormatWithSymbol($localeData, $defaultSet);
        return $this->clearPriceFormat($priceFormat);
    }

    /**
     * Removes currency symbol from the price format.
     * Returns in a format like '#,##0.00'
     *
     * @param $priceFormat
     * @return null|string|string[]
     */
    private function clearPriceFormat($priceFormat)
    {
        // Remove extra part, e.g. "¤ #,##0.00; (¤ #,##0.00)" => "¤ #,##0.00".
        if (($pos = strpos($priceFormat, ';')) !== false) {
            $priceFormat = substr($priceFormat, 0, $pos);
        }
        // Remove all other characters than "0", "#", "." and ",",
        return preg_replace('/[^0\#\.,]/', '', $priceFormat);
    }

    /**
     * Returns the price format with symbol position, thousands and decimal digits
     *
     * @param $localeData
     * @param $defaultSet
     * @return mixed
     * @suppress PhanTypeArraySuspicious
     */
    private function buildPriceFormatWithSymbol($localeData, $defaultSet)
    {
        if ($localeData['NumberElements'][$defaultSet]['patterns']['currencyFormat']) {
            return $localeData['NumberElements']['latn']['patterns']['currencyFormat'];
        }
        return explode(';', $localeData['NumberPatterns'][1])[0];
    }

    /**
     * Returns the symbol used to separate decimal digits
     *
     * @param $localeData
     * @param $defaultSet
     * @return mixed
     * @suppress PhanTypeArraySuspicious
     */
    private function buildDecimalSymbol($localeData, $defaultSet)
    {
        if ($localeData['NumberElements'][$defaultSet]['symbols']['decimal']) {
            return $localeData['NumberElements']['latn']['symbols']['decimal'];
        }
        return $localeData['NumberElements'][0];
    }

    /**
     * Returns the symbol used to separate thousands
     *
     * @param $localeData
     * @param $defaultSet
     * @return mixed
     * @suppress PhanTypeArraySuspicious
     */
    private function buildGroupSymbol($localeData, $defaultSet)
    {
        if ($localeData['NumberElements'][$defaultSet]['symbols']['group']) {
            return $localeData['NumberElements']['latn']['symbols']['group'];
        }
        return $localeData['NumberElements'][1];
    }
}
