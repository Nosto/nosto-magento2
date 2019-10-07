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

namespace Nosto\Tagging\Model\Service\Sync;

use Exception;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Api\Data\ProductCacheInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Cache as NostoProductIndex;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollectionFactory;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Util\Serializer\ProductSerializer;

class SyncService extends AbstractService
{
    const API_BATCH_SIZE = 50;
    const PRODUCT_DELETION_BATCH_SIZE = 100;
    const BENCHMARK_SYNC_NAME = 'nosto_product_sync';
    const BENCHMARK_SYNC_BREAKPOINT = 1;
    const BENCHMARK_DELETE_NAME = 'nosto_product_delete';
    const BENCHMARK_DELETE_BREAKPOINT = 1;
    const RESPONSE_TIMEOUT = 60;

    /** @var CacheRepository */
    private $cacheRepository;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var CacheCollectionFactory */
    private $nostoCacheCollectionFactory;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /** @var ProductSerializer */
    private $productSerializer;

    /**
     * Index constructor.
     * @param CacheRepository $cacheRepository
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param CacheCollectionFactory $nostoCacheCollectionFactory
     * @param NostoDataHelper $nostoDataHelper
     * @param ProductSerializer $productSerializer
     */
    public function __construct(
        CacheRepository $cacheRepository,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        CacheCollectionFactory $nostoCacheCollectionFactory,
        NostoDataHelper $nostoDataHelper,
        ProductSerializer $productSerializer
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->cacheRepository = $cacheRepository;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoCacheCollectionFactory = $nostoCacheCollectionFactory;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->productSerializer = $productSerializer;
    }

    /**
     * @param CacheCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function syncIndexedProducts(CacheCollection $collection, Store $store)
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

        /** @var CacheCollection $page */
        foreach ($iterator as $page) {
            $this->checkMemoryConsumption('product sync');
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout(self::RESPONSE_TIMEOUT);
            /** @var ProductCacheInterface $productIndex */
            foreach ($page as $productIndex) {
                $productData = $productIndex->getProductData();
                if (empty($productData) && !$productIndex->getIsDirty()) {
                    throw new NostoException(
                        'Something is wrong in the nosto product index table.
                        Product do not have data nor is marked as dirty'
                    );
                }
                if (empty($productData)) {
                    continue; // Do not sync products with null data
                }
                $this->getLogger()->debug(
                    sprintf('Upserting product "%s"', $productIndex->getProductId()),
                    ['store' => $productIndex->getStoreId()]
                );
                $op->addProduct(
                    $this->productSerializer->fromString(
                        $productData
                    )
                );
            }
            try {
                $this->getLogger()->debug('Upserting batch');
                $op->upsert();
                $this->cacheRepository->markAsInSyncCurrentItemsByStore($page, $store);
                $this->tickBenchmark(self::BENCHMARK_SYNC_NAME);
            } catch (Exception $upsertException) {
                $this->getLogger()->exception($upsertException);
            }
        }

        $this->logBenchmarkSummary(self::BENCHMARK_SYNC_NAME, $store);
    }

    /**
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     */
    public function syncDeletedProducts(Store $store)
    {
        try {
            $this->purgeDeletedProducts($store);
            $this->getLogger()->info(
                sprintf(
                    'Removed products from index for store %s',
                    $store->getCode()
                )
            );
        } catch (NostoException $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * @param int[] $productIds
     * @param Store $store
     * @return void
     */
    public function markAsInSyncByProductIdsAndStoreId(array $productIds, Store $store)
    {
        try {
            $this->cacheRepository->markAsInSync($productIds, $store);
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * Discontinues products in Nosto and removes indexed products from Nosto product index
     *
     * @param CacheCollection $collection
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function deleteIndexedProducts(CacheCollection $collection, Store $store)
    {
        if ($collection->getSize() === 0) {
            return;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $this->startBenchmark(self::BENCHMARK_DELETE_NAME, self::BENCHMARK_DELETE_BREAKPOINT);
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var CacheCollection $page */
        foreach ($iterator as $page) {
            $this->checkMemoryConsumption('product delete');
            $ids = [];
            /* @var $indexedProduct NostoProductIndex */
            foreach ($page as $indexedProduct) {
                $ids[] = $indexedProduct->getProductId();
            }
            try {
                $op = new DeleteProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
                $op->setResponseTimeout(self::RESPONSE_TIMEOUT);
                $op->setProductIds($ids);
                $op->delete(); // @codingStandardsIgnoreLine
                $this->cacheRepository->deleteCurrentItemsByStore($page, $store);
                $this->tickBenchmark(self::BENCHMARK_DELETE_NAME);
            } catch (Exception $e) {
                $this->getLogger()->exception($e);
            }
        }

        $this->logBenchmarkSummary(self::BENCHMARK_DELETE_NAME, $store);
    }

    /**
     * Fetches deleted products from the product index, sends those to Nosto
     * and deletes the deleted rows from database
     *
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function purgeDeletedProducts(Store $store)
    {
        $collection = $this->nostoCacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIsDeletedFilter()
            ->addStoreFilter($store);
        $this->deleteIndexedProducts($collection, $store);
    }
}
