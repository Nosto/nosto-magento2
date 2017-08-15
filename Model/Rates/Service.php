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

namespace Nosto\Tagging\Model\Rates;

use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Nosto\Operation\SyncRates;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Rates\Builder as NostoExchangeRatesBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Service
{
    private $logger;
    private $eventManager;
    private $nostoExchangeRatesBuilder;
    private $nostoHelperAccount;

    /**
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoExchangeRatesBuilder $nostoExchangeRatesBuilder
     */
    public function __construct(
        NostoLogger $logger,
        ManagerInterface $eventManager,
        NostoHelperAccount $nostoHelperAccount,
        NostoExchangeRatesBuilder $nostoExchangeRatesBuilder
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoExchangeRatesBuilder = $nostoExchangeRatesBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    /**
     * Sends a currency exchange rate update request to Nosto via the API. Checks if multi currency
     * is enabled for the store before attempting to send the exchange rates.
     *
     * @param Store $store the store for which the rates are to be updated.
     * @return bool a boolean value indicating whether the operation was successful
     */
    public function update(Store $store)
    {
        if ($account = $this->nostoHelperAccount->findAccount($store)) {
            $rates = $this->nostoExchangeRatesBuilder->build($store);
            if (empty($rates->getRates())) {
                $this->logger->debug('Skipping update; no multi-currency configured for ' .
                    $store->getName());

                return false;
            }

            try {
                /** @noinspection PhpParamsInspection */
                $this->logger->info(
                    sprintf(
                        'Found %d currencies for store ',
                        count($rates)
                    )
                );
                $service = new SyncRates($account);

                return $service->update($rates);
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        } else {
            $this->logger->debug(
                'Skipping update; an account doesn\'t exist for ' .
                $store->getName()
            );
        }

        return false;
    }
}
