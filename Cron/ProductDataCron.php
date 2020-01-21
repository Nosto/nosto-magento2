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
use Magento\Framework\Exception\LocalizedException;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Cache;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\Service\Cache\CacheService;
use Nosto\Tagging\Util\PagingIterator;

/**
 * Cronjob class that rebuilds the product index
 *
 * @package Nosto\Tagging\Cron
 */
class ProductDataCron
{
    /** @var Logger */
    protected $logger;

    /** @var NostoAccountHelper */
    private $nostoAccountHelper;

    /** @var CacheRepository */
    private $cacheRepository;

    /** @var int */
    private $batchSize;

    /** @var CacheService */
    private $cacheService;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /**
     * ProductDataCron constructor.
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoDataHelper $nostoDataHelper
     * @param CacheRepository $cacheRepository
     * @param CacheService $cacheService
     * @param Logger $logger
     * @param $batchSize
     */
    public function __construct(
        NostoAccountHelper $nostoAccountHelper,
        NostoDataHelper $nostoDataHelper,
        CacheRepository $cacheRepository,
        CacheService $cacheService,
        Logger $logger,
        $batchSize
    ) {
        $this->logger = $logger;
        $this->cacheRepository = $cacheRepository;
        $this->nostoAccountHelper = $nostoAccountHelper;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->cacheService = $cacheService;
        $this->batchSize = $batchSize;
    }

    /**
     * Executes a cron job for rebuilding invalid product data
     * @throws Exception
     */
    public function execute()
    {
        $stores = $this->nostoAccountHelper->getStoresWithNosto();
        $this->logger->debug(
            sprintf(
                'Starting to rebuild product cache for dirty products - Nosto is installed for %d stores',
                count($stores)
            )
        );
        foreach ($stores as $store) {
            if ($this->nostoDataHelper->isProductDataBuildInCronEnabled($store) === false) {
                continue;
            }
            $productCollection = $this->cacheRepository->getOutOfSyncInStore($store);
            $productCollection->setPageSize($this->batchSize);
            $this->logger->info(
                sprintf(
                    'Starting to rebuild %d dirty products in a cron job with batch size %d for store %s',
                    $productCollection->getSize(),
                    $this->batchSize,
                    $store->getCode()
                )
            );
            $iterator = new PagingIterator($productCollection);
            foreach ($iterator as $page) {
                $ids = $page->toArray([Cache::ID])['items'];
                try {
                    $this->cacheService->generateProductsInStore($store, $ids);
                } catch (MemoryOutOfBoundsException $e) {
                    $this->logger->error($e->getMessage());
                } catch (NostoException $e) {
                    $this->logger->error($e->getMessage());
                } catch (LocalizedException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
            $this->logger->info(
                sprintf(
                    'Finished rebuild for store %s',
                    $store->getCode()
                )
            );
        }
        $this->logger->debug('Product data cron ran');
    }
}
