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

namespace Nosto\Tagging\Model\Meta\Account\Settings\Currencies;

use Exception;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\Store;
use Nosto\Model\Format;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Model\Operation\GetSettings;

class Builder
{
    /** @var NostoLogger */
    private NostoLogger $logger;

    /** @var ManagerInterface */
    private ManagerInterface $eventManager;

    /** @var CurrencyFactory */
    private CurrencyFactory $currencyFactory;

    /** @var LocaleResolver */
    private LocaleResolver $localeResolver;

    /** @var NostoHelperCurrency */
    private NostoHelperCurrency $nostoCurrencyHelper;

    /** @var NostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var CacheInterface */
    private CacheInterface $cache;

    /** @var SerializerInterface */
    private SerializerInterface $serializer;

    private const CACHE_KEY_PREFIX = 'nosto_currency_formats_';
    private const CACHE_TTL = 3600;

    /* List of zero decimal currencies in compliance with ISO-4217 */
    public const ZERO_DECIMAL_CURRENCIES = [
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
     * @param NostoHelperCurrency $nostoCurrencyHelper
     * @param LocaleResolver $localeResolver
     * @param NostoHelperAccount $nostoHelperAccount
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        NostoLogger         $logger,
        ManagerInterface    $eventManager,
        CurrencyFactory     $currencyFactory,
        NostoHelperCurrency $nostoCurrencyHelper,
        LocaleResolver      $localeResolver,
        NostoHelperAccount  $nostoHelperAccount,
        CacheInterface      $cache,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->currencyFactory = $currencyFactory;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->localeResolver = $localeResolver;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->cache = $cache;
        $this->serializer = $serializer;
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
            $nostoCurrencies = $this->fetchNostoCurrencyFormats($store);

            $storeLocale = $store->getConfig('general/locale/code');
            $localeCode = $storeLocale ?: $this->localeResolver->getLocale();
            $localeData = (new DataBundle())->get($localeCode);
            $defaultSet = $localeData['NumberElements']['default'] ?: 'latn';

            $priceFormat = $this->getPriceFormat($localeData, $defaultSet);
            $decimalSymbol = $this->buildDecimalSymbol($localeData, $defaultSet);
            $groupSymbol = $this->buildGroupSymbol($localeData, $defaultSet);
            $precision = $this->getDecimalPrecision($priceFormat);

            // Get other active currencies when multicurrency is enabled
            if ($this->nostoCurrencyHelper->exchangeRatesInUse($store)) {
                $currencyCodes = $store->getAvailableCurrencyCodes(true);
            } else {
                $currencyCodes = [$store->getBaseCurrencyCode()];
            }
            if (is_array($currencyCodes) && !empty($currencyCodes)) {
                foreach ($currencyCodes as $currencyCode) {
                    // Prioritize format already configured in Nosto over locally derived one
                    if (isset($nostoCurrencies[$currencyCode])) {
                        $currencies[$currencyCode] = $nostoCurrencies[$currencyCode];
                        continue;
                    }
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
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_currencies_load_after', ['currencies' => $currencies]);

        return $currencies;
    }

    /**
     * Fetches currency formats already configured in Nosto for this store's account.
     * Results are cached for CACHE_TTL seconds to avoid repeated API calls.
     * Returns an empty array when no account exists or the request fails.
     *
     * @param Store $store
     * @return Format[]
     */
    private function fetchNostoCurrencyFormats(Store $store): array
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        if (!$account) {
            return [];
        }

        $cacheKey = self::CACHE_KEY_PREFIX . $store->getId();
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return $this->deserializeFormats($this->serializer->unserialize($cached));
        }

        try {
            $formats = (new GetSettings($account))->getCurrencyFormats();
            $this->cache->save(
                $this->serializer->serialize($this->serializeFormats($formats)),
                $cacheKey,
                [],
                self::CACHE_TTL
            );
            return $formats;
        } catch (Exception $e) {
            $this->logger->exception($e);
            return [];
        }
    }

    /**
     * @param Format[] $formats
     * @return array
     */
    private function serializeFormats(array $formats): array
    {
        $data = [];
        foreach ($formats as $code => $format) {
            $data[$code] = [
                'currency_before_amount' => $format->getCurrencyBeforeAmount(),
                'currency_token' => $format->getCurrencyToken(),
                'decimal_character' => $format->getDecimalCharacter(),
                'grouping_separator' => $format->getGroupingSeparator(),
                'decimal_places' => $format->getDecimalPlaces(),
            ];
        }
        return $data;
    }

    /**
     * @param array $data
     * @return Format[]
     */
    private function deserializeFormats(array $data): array
    {
        $formats = [];
        foreach ($data as $code => $item) {
            $formats[$code] = new Format(
                (bool)$item['currency_before_amount'],
                $item['currency_token'],
                $item['decimal_character'],
                $item['grouping_separator'],
                (int)$item['decimal_places']
            );
        }
        return $formats;
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
        return preg_replace('/[^0#.]/', '', $priceFormat);
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
            return $localeData['NumberElements'][$defaultSet]['patterns']['currencyFormat'];
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
            return $localeData['NumberElements'][$defaultSet]['symbols']['decimal'];
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
            return $localeData['NumberElements'][$defaultSet]['symbols']['group'];
        }
        return $localeData['NumberElements'][1];
    }
}
