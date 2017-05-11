<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Meta\Account\Settings\Currencies;

use Exception;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Format;
use Psr\Log\LoggerInterface;

class Builder
{
    private $logger;
    private $eventManager;
    private $currencyFactory;
    private $localeResolver;

    /**
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyFactory $currencyFactory
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        LoggerInterface $logger,
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
     * @param StoreInterface|Store $store
     * @return array
     * @suppress PhanTypeArraySuspicious
     */
    public function build(StoreInterface $store)
    {
        $currencies = array();

        try {
            $storeLocale = $store->getConfig('general/locale/code');

            $localeCode = $storeLocale ?: $this->localeResolver->getLocale();
            $localeData = (new DataBundle())->get($localeCode);
            $defaultSet = $localeData['NumberElements']['default'] ?: 'latn';
            $priceFormat = $localeData['NumberElements'][$defaultSet]['patterns']['currencyFormat']
                ?: ($localeData['NumberElements']['latn']['patterns']['currencyFormat']
                    ?: explode(';', $localeData['NumberPatterns'][1])[0]);

            $decimalSymbol = $localeData['NumberElements'][$defaultSet]['symbols']['decimal']
                ?: ($localeData['NumberElements']['latn']['symbols']['decimal']
                    ?: $localeData['NumberElements'][0]);

            $groupSymbol = $localeData['NumberElements'][$defaultSet]['symbols']['group']
                ?: ($localeData['NumberElements']['latn']['symbols']['group']
                    ?: $localeData['NumberElements'][1]);

            // Remove extra part, e.g. "¤ #,##0.00; (¤ #,##0.00)" => "¤ #,##0.00".
            if (($pos = strpos($priceFormat, ';')) !== false) {
                $priceFormat = substr($priceFormat, 0, $pos);
            }

            // Check if the currency symbol is before or after the amount.
            $symbolPosition = strpos(trim($priceFormat), '¤') === 0;

            // Remove all other characters than "0", "#", "." and ",",
            $priceFormat = preg_replace('/[^0\#\.,]/', '', $priceFormat);
            // Calculate the decimal precision.
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

            $currencyCodes = $store->getAvailableCurrencyCodes(true);
            if (is_array($currencyCodes) && !empty($currencyCodes)) {
                foreach ($currencyCodes as $currencyCode) {

                    $currency = $this->currencyFactory->create()->load($currencyCode);
                    $currencies[$currency->getCode()] = new Format(
                        $symbolPosition,
                        $currency->getCurrencySymbol(),
                        $decimalSymbol,
                        $groupSymbol,
                        $precision
                    );
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e->__toString());
        }

        $this->eventManager->dispatch('nosto_currencies_load_after', ['currencies' => $currencies]);

        return $currencies;
    }
}
