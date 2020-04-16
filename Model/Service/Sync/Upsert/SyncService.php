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

namespace Nosto\Tagging\Model\Service\Sync\Upsert;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Cache\CacheService;
use Nosto\Tagging\Model\Service\Product\ProductServiceInterface;
use Nosto\Tagging\Util\PagingIterator;

/**
 * Class SyncService
 */
class SyncService extends AbstractService
{
    const BENCHMARK_SYNC_NAME = 'nosto_product_upsert';
    const BENCHMARK_SYNC_BREAKPOINT = 1;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /** @var ProductServiceInterface */
    private $productService;

    /** @var CacheService */
    private $cacheService;

    /** @var int */
    private $apiBatchSize;

    /** @var int */
    private $apiTimeout;

    /**
     * Index constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param ProductServiceInterface $productService
     * @param CacheService $cacheService
     * @param $apiBatchSize
     * @param $apiTimeout
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        ProductServiceInterface $productService,
        CacheService $cacheService,
        $apiBatchSize,
        $apiTimeout
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->productService = $productService;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->cacheService = $cacheService;
        $this->apiBatchSize = $apiBatchSize;
        $this->apiTimeout = $apiTimeout;
    }

    /**
     * @param ProductCollection $collection
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function syncProducts(ProductCollection $collection, Store $store)
    {
        if (!$this->nostoDataHelper->isProductUpdatesEnabled($store)) {
            $this->logDebugWithStore(
                'Nosto product sync is disabled - skipping upserting products to Nosto',
                $store
            );
            return;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        $this->startBenchmark(self::BENCHMARK_SYNC_NAME, self::BENCHMARK_SYNC_BREAKPOINT);

        $collection->setPageSize($this->apiBatchSize);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $productIdsInBatch = [];
            $this->checkMemoryConsumption('product sync');
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout($this->apiTimeout);
            /** @var Product $product */
            foreach ($page as $product) {
                $productIdsInBatch[] = $product->getId();
                $nostoProduct = $this->productService->getProduct($product, $store);
                if ($nostoProduct === null) {
                    throw new NostoException('Could not get product from the product service.');
                }
                try {
                    $op->addProduct($nostoProduct);
                    $this->cacheService->upsert($nostoProduct, $store);
                    $this->tickBenchmark(self::BENCHMARK_SYNC_NAME);
                } catch (\Exception $e) {
                    $this->getLogger()->exception($e);
                }
            }
            try {
                $this->logDebugWithStore(
                    sprintf(
                        'Upserting batch of %d (%s) - API timeout is set to %d seconds',
                        $this->apiBatchSize,
                        implode(',', $productIdsInBatch),
                        $this->apiTimeout
                    ),
                    $store
                );
                $op->upsert();
            } catch (Exception $upsertException) {
                $this->getLogger()->exception($upsertException);
            }
        }
        $this->logBenchmarkSummary(self::BENCHMARK_SYNC_NAME, $store, $this);
    }
}
