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
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Product\ProductSerializerInterface;
use Nosto\Tagging\Model\Service\Product\ProductServiceInterface;
use Nosto\Tagging\Util\PagingIterator;
use Magento\Catalog\Model\Product;

/**
 * Class SyncService
 */
class SyncService extends AbstractService
{
    const API_BATCH_SIZE = 50;
    const BENCHMARK_SYNC_NAME = 'nosto_product_sync';
    const BENCHMARK_SYNC_BREAKPOINT = 1;
    const RESPONSE_TIMEOUT = 60;

    /** @var CacheRepository */
    private $cacheRepository;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /** @var ProductSerializerInterface */
    private $productSerializer;

    /** @var ProductServiceInterface */
    private $productService;

    /**
     * Index constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param ProductServiceInterface $productService
     * @param ProductSerializerInterface $productSerializer
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        ProductServiceInterface $productService,
        ProductSerializerInterface $productSerializer
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->productService = $productService;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->productSerializer = $productSerializer;
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
            $this->getLogger()->info(
                'Nosto product sync is disabled - skipping upserting products to Nosto'
            );
            return;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        $this->startBenchmark(self::BENCHMARK_SYNC_NAME, self::BENCHMARK_SYNC_BREAKPOINT);

        $collection->setPageSize(self::API_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $this->checkMemoryConsumption('product sync');
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout(self::RESPONSE_TIMEOUT);
            /** @var Product $product */
            foreach ($page as $product) {
                $nostoProduct = $this->productService->getProduct($product, $store);
                if ($nostoProduct === null) {
                    throw new NostoException('Could not get product from the product service.');
                }
                $this->getLogger()->debug(
                    sprintf('Upserting product "%s"', $product->getProductId()),
                    ['store' => $product->getStoreId()]
                );
                try {
                    $op->addProduct($nostoProduct);
                    // TODO: Add cache update logic here if the flag is here
                } catch (\Exception $e) {
                    $this->getLogger()->exception($e);
                }
            }
            try {
                $this->getLogger()->debug('Upserting batch');
                $op->upsert();
                $this->tickBenchmark(self::BENCHMARK_SYNC_NAME);
            } catch (Exception $upsertException) {
                $this->getLogger()->exception($upsertException);
            }
        }

        $this->logBenchmarkSummary(self::BENCHMARK_SYNC_NAME, $store);
    }
}
