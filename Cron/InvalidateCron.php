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

use DateInterval;
use DateTime;
use Exception;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;

/**
 * Cronjob class that periodically invalidates Nosto indexed data for each of the store views
 */
class InvalidateCron
{
    /** @var Logger */
    protected $logger;

    /** @var NostoAccountHelper */
    private $nostoAccountHelper;

    /** @var CacheRepository */
    private $cacheRepository;

    /** @var TimezoneInterface */
    private $timezoneInterface;

    /** @var int */
    private $intervalHours;

    /** @var int */
    private $productLimit;

    /**
     * Invalidate constructor.
     *
     * @param Logger $logger
     * @param CacheRepository $cacheRepository
     * @param NostoAccountHelper $nostoAccountHelper
     * @param TimezoneInterface $timezoneInterface
     * @param int $intervalHours
     * @param int $productLimit
     */
    public function __construct(
        Logger $logger,
        CacheRepository $cacheRepository,
        NostoAccountHelper $nostoAccountHelper,
        TimezoneInterface $timezoneInterface,
        $intervalHours,
        $productLimit
    ) {
        $this->logger = $logger;
        $this->cacheRepository = $cacheRepository;
        $this->nostoAccountHelper = $nostoAccountHelper;
        $this->timezoneInterface = $timezoneInterface;
        $this->intervalHours = $intervalHours;
        $this->productLimit = $productLimit;
    }

    /**
     * Executes cron job
     * @throws Exception
     */
    public function execute()
    {
        if (!$this->productLimit || !is_numeric($this->productLimit)) {
            $this->logger->debug(
                sprintf(
                    'Product limit for invalidate cache cron is set to %s. Not invalidating any products.',
                    $this->productLimit
                )
            );
            return;
        }
        $stores = $this->nostoAccountHelper->getStoresWithNosto();
        $this->logger->debug(
            sprintf(
                'Starting to invalidate product cache - Nosto is installed for %d stores',
                count($stores)
            )
        );
        foreach ($stores as $store) {
            $productCacheCollection = $this->cacheRepository->getByLastUpdatedAndStore(
                $store,
                $this->getTimeOffset(),
                $this->productLimit
            );
            $updatedCount = $this->cacheRepository->markAsIsDirtyItemsByStore($productCacheCollection, $store);
            $this->logger->debug(
                sprintf(
                    'Invalidated (set dirty) %d products for store %s by invalidate cron.' .
                    ' Product limit is %d and interval is %d',
                    $updatedCount,
                    $store->getCode(),
                    $this->productLimit,
                    $this->intervalHours
                )
            );
        }
    }

    /**
     * @return DateTime
     * @throws Exception
     */
    private function getTimeOffset()
    {
        return $this->timezoneInterface
            ->date()
            ->sub(new DateInterval(sprintf('PT%dH', $this->intervalHours)));
    }
}
