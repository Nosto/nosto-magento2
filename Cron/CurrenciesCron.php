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

namespace Nosto\Tagging\Cron;

use Exception;
use Nosto\Operation\UpdateSettings;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Meta\Account\Settings\Builder as NostoSettingsBuilder;
use Nosto\Tagging\Model\Meta\Account\Settings\Currencies\Builder as NostoCurrenciesBuilder;

/**
 * Cronjob class that periodically updates currencies used in the store
 */
class CurrenciesCron
{
    protected NostoLogger $logger;
    private NostoCurrenciesBuilder $nostoCurrenciesBuilder;
    private NostoHelperScope $nostoHelperScope;
    private NostoHelperAccount $nostoHelperAccount;
    private NostoSettingsBuilder $nostoSettingsBuilder;

    /**
     * Rates constructor.
     *
     * @param NostoLogger $logger
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoCurrenciesBuilder $nostoCurrenciesBuilder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoSettingsBuilder $nostoSettingsBuilder
     */
    public function __construct(
        NostoLogger $logger,
        NostoHelperScope $nostoHelperScope,
        NostoCurrenciesBuilder $nostoCurrenciesBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoSettingsBuilder $nostoSettingsBuilder
    ) {
        $this->logger = $logger;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoCurrenciesBuilder = $nostoCurrenciesBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoSettingsBuilder = $nostoSettingsBuilder;
    }

    public function execute(): void
    {
        $this->logger->info('Updating currencies to Nosto for all store views');
        foreach ($this->nostoHelperScope->getStores(false) as $store) {
            $this->logger->info('Updating currencies for ' . $store->getName());
            if (!$account = $this->nostoHelperAccount->findAccount($store)) {
                $this->logger->info(sprintf(
                    'Skipping update; an account doesn\'t exist for %s',
                    $store->getName()
                ));
                continue;
            }

            $settings = $this->nostoSettingsBuilder->build($store);
            try {
                $settings->setCurrencies($this->nostoCurrenciesBuilder->build($store));
                $service = new UpdateSettings($account);
                $service->update($settings);
            } catch (Exception $e) {
                $this->logger->warning(sprintf(
                    'Unable to update the currencies for the store view %s. Message was: %s',
                    $store->getName(),
                    $e->getMessage()
                ));
            }
        }
    }
}
