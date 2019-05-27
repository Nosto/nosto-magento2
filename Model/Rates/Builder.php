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

namespace Nosto\Tagging\Model\Rates;

use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Nosto\Object\ExchangeRate;
use Nosto\Object\ExchangeRateCollection;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private $logger;
    private $eventManager;
    private $currencyFactory;

    /**
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        NostoLogger $logger,
        ManagerInterface $eventManager,
        CurrencyFactory $currencyFactory
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->currencyFactory = $currencyFactory;
    }

    /**
     * Builds the collection of exchange-rates for the specified store view. The collection of rates
     * contains rates from the store's base currency to each of the other currencies.
     *
     * @param Store $store the store view for which to build the exchange rates
     * @return ExchangeRateCollection the collection of exchange rates for the store
     */
    public function build(Store $store)
    {
        $exchangeRates = new ExchangeRateCollection();

        try {
            $currencyCodes = $store->getAvailableCurrencyCodes(true);
            $baseCurrencyCode = $store->getBaseCurrencyCode();

            /** @var Currency $currencyModel */
            $currencyModel = $this->currencyFactory->create();
            $rates = $currencyModel->getCurrencyRates($baseCurrencyCode, $currencyCodes);
            foreach ($rates as $code => $rate) {
                if ($baseCurrencyCode === $code) {
                    continue; // Skip base currency.
                }

                $this->logger->info(sprintf(
                    'The rate from %s to %s is %f',
                    $baseCurrencyCode,
                    $code,
                    $rate
                ));
                $exchangeRates->addRate($code, new ExchangeRate($code, $rate));
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch(
            'nosto_exchange_rates_load_after',
            ['rates' => $exchangeRates]
        );

        return $exchangeRates;
    }
}
