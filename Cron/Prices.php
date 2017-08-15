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

namespace Nosto\Tagging\Cron;

use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Product\Service as NostoProductService;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Psr\Log\LoggerInterface;

/**
 * Cronjob class that periodically updates exchange-rates to Nosto for each of the store views,
 * provided that multiple-currencies are configured for that store view.
 *
 * @package Nosto\Tagging\Cron
 */
class Prices
{
    protected $logger;
    private $nostoProductService;
    private $nostoProductRepository;
    private $nostoHelperScope;
    private $nostoHelperData;

    /**
     * Prices constructor.
     *
     * @param LoggerInterface $logger
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoProductService $nostoProductService
     * @param NostoProductRepository $nostoProductRepository
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        LoggerInterface $logger,
        NostoHelperScope $nostoHelperScope,
        NostoProductService $nostoProductService,
        NostoProductRepository $nostoProductRepository,
        NostoHelperData $nostoHelperData
    ) {
        $this->logger = $logger;
        $this->nostoProductService = $nostoProductService;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperData = $nostoHelperData;
    }

    public function execute()
    {
        // This cron should be only ran for Magento 2 CE. Enterprise edition's staging
        // functionality works out-of-the-box with custom indexer
        if (strtolower($this->nostoHelperData->getPlatformEdition()) === 'community') {
            $this->logger->info('Updating advanced pricing');
            $products
                = $this->nostoProductRepository->getWithActivePricingSchedule();
            if ($products->getTotalCount() > 0) {
                try {
                    $this->nostoProductService->update($products->getItems());
                    $this->logger->debug(
                        sprintf(
                            'Found %d products',
                            $products->getTotalCount()
                        )
                    );
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf(
                            'Failed to update products. Error was %s',
                            $e->getMessage()
                        )
                    );
                }
            }
        }
    }
}
