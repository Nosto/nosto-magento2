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

namespace Nosto\Tagging\Cron;

use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Rates\Service as NostoRatesService;
use Nosto\Tagging\Logger\Logger as NostoLogger;

/**
 * Cronjob class that periodically updates exchange-rates to Nosto for each of the store views,
 * provided that multiple-currencies are configured for that store view.
 *
 * @package Nosto\Tagging\Cron
 */
class Rates
{
    protected $logger;
    private $nostoRatesService;
    private $nostoHelperScope;

    /**
     * Rates constructor.
     *
     * @param NostoLogger $logger
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoRatesService $nostoRatesService
     */
    public function __construct(
        NostoLogger $logger,
        NostoHelperScope $nostoHelperScope,
        NostoRatesService $nostoRatesService
    ) {
        $this->logger = $logger;
        $this->nostoRatesService = $nostoRatesService;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    public function execute()
    {
        $this->logger->info('Updating exchange rates to Nosto for all store views');
        foreach ($this->nostoHelperScope->getStores(false) as $store) {
            $this->logger->info('Updating exchange rates for ' . $store->getName());
            if ($this->nostoRatesService->update($store)) {
                $this->logger->info('Successfully updated the exchange rates for the store view');
            } else {
                $this->logger->warning('Unable to update the exchange rates for the store view');
            }
        }
    }
}
