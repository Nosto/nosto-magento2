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

namespace Nosto\Tagging\Model\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\Index\Builder;
use Nosto\Tagging\Model\Product\Index\Index as NostoProductIndex;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as NostoIndexCollectionFactory;
use Nosto\Tagging\Util\Iterator;
use Nosto\Tagging\Util\Product as ProductUtil;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class Index extends AbstractService
{
    const PRODUCT_DATA_BATCH_SIZE = 100;
    const PRODUCT_DELETION_BATCH_SIZE = 100;
    const BENCHMARK_BREAKPOINT_INVALIDATE = 100;
    const BENCHMARK_BREAKPOINT_REBUILD = 10;
    const BENCHMARK_NAME_INVALIDATE = 'nosto_index_invalidate';
    const BENCHMARK_NAME_REBUILD = 'nosto_index_rebuild';

    /** @var IndexRepository */
    private $indexRepository;

    /** @var Builder */
    private $indexBuilder;

    /** @var ProductRepository */
    private $productRepository;

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoIndexCollectionFactory */
    private $nostoIndexCollectionFactory;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /** @var Sync */
    private $nostoSyncService;

    /**
     * Index constructor.
     * @param IndexRepository $indexRepository
     * @param Builder $indexBuilder
     * @param ProductRepository $productRepository
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $logger
     * @param NostoIndexCollectionFactory $nostoIndexCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     * @param NostoDataHelper $nostoDataHelper
     * @param Sync $nostoSyncService
     */
    public function __construct(
        IndexRepository $indexRepository,
        Builder $indexBuilder,
        ProductRepository $productRepository,
        NostoProductBuilder $nostoProductBuilder,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger,
        NostoIndexCollectionFactory $nostoIndexCollectionFactory,
        TimezoneInterface $magentoTimeZone,
        NostoDataHelper $nostoDataHelper,
        Sync $nostoSyncService
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->indexRepository = $indexRepository;
        $this->indexBuilder = $indexBuilder;
        $this->productRepository = $productRepository;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoIndexCollectionFactory = $nostoIndexCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
        $this->nostoSyncService = $nostoSyncService;
    }

    /**
     * Handles only the first step of indexing
     * Create one if row does not exits
     * Else set row to dirty
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
     */
    public function invalidateOrCreate(ProductCollection $collection, Store $store)
    {
        $this->startBenchmark(
            self::BENCHMARK_NAME_INVALIDATE,
            self::BENCHMARK_BREAKPOINT_INVALIDATE
        );
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->each(function (Product $item) use ($store) {
            $this->updateOrCreateDirtyEntity($item, $store);
            $this->tickBenchmark(self::BENCHMARK_NAME_INVALIDATE);
        });
        $this->logBenchmarkSummary(self::BENCHMARK_NAME_INVALIDATE, $store);
    }

    /**
     * @param Product $product
     * @param Store $store
     */
    public function updateOrCreateDirtyEntity(Product $product, Store $store)
    {
        $indexedProduct = $this->indexRepository->getByProductIdAndStoreId($product->getId(), $store->getId());
        try {
            if ($indexedProduct === null) {
                /* @var Product $fullProduct */
                $fullProduct = $this->loadMagentoProduct($product->getId(), $store->getId());
                $indexedProduct = $this->indexBuilder->build($fullProduct, $store);
                $indexedProduct->setIsDirty(false);
            }
            $indexedProduct->setIsDirty(true);
            $indexedProduct->setUpdatedAt($this->magentoTimeZone->date());
            $this->indexRepository->save($indexedProduct);
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function indexDirtyProducts(NostoIndexCollection $collection, Store $store)
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $this->rebuildDirtyProducts($collection, $store);
        $this->nostoSyncService->syncIndexedProducts($collection, $store);
        $this->nostoSyncService->syncDeletedProducts($store);
    }

    /**
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function rebuildDirtyProducts(NostoIndexCollection $collection, Store $store)
    {
        $this->startBenchmark(
            self::BENCHMARK_NAME_REBUILD,
            self::BENCHMARK_BREAKPOINT_REBUILD
        );
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->each(function (NostoProductIndex $item) {
            if ($item->getIsDirty() === NostoProductIndex::DB_VALUE_BOOLEAN_TRUE) {
                $this->rebuildDirtyProduct($item);
                $this->tickBenchmark(self::BENCHMARK_NAME_REBUILD);
            }
            $this->checkMemoryConsumption('product rebuild');
        });
        $this->logBenchmarkSummary(self::BENCHMARK_NAME_REBUILD, $store);
    }

    /**
     * Rebuilds a dirty indexed product data & defines it as out of sync
     * if Nosto product data changed
     *
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface|null
     */
    public function rebuildDirtyProduct(ProductIndexInterface $productIndex)
    {
        try {
            /* @var Product $magentoProduct */
            $magentoProduct = $this->loadMagentoProduct(
                $productIndex->getProductId(),
                $productIndex->getStoreId()
            );
            $store = $this->nostoHelperScope->getStore($productIndex->getStoreId());
            $nostoProduct = $this->nostoProductBuilder->build($magentoProduct, $store);
            $nostoIndexedProduct = $productIndex->getNostoProduct();
            if ($nostoIndexedProduct instanceof NostoProductInterface === false ||
                (
                    $nostoProduct instanceof NostoProductInterface
                    && !ProductUtil::isEqual($nostoProduct, $nostoIndexedProduct)
                )
            ) {
                $productIndex->setNostoProduct($nostoProduct);
                $productIndex->setInSync(false);
            }
            $productIndex->setIsDirty(false);
            $this->indexRepository->save($productIndex);
            return $productIndex;
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
            return null;
        }
    }

    /**
     * @param ProductCollection $collection
     * @param array $ids
     * @param Store $store
     * @throws NostoException
     */
    public function markProductsAsDeletedByDiff(ProductCollection $collection, array $ids, Store $store)
    {
        $uniqueIds = array_unique($ids);
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->each(static function (Product $magentoProduct) use (&$uniqueIds) {
            $key = array_search($magentoProduct->getId(), $uniqueIds);
            if (is_numeric($key)) {
                unset($uniqueIds[$key]);
            }
        });
        // Flag the rest of the ids as deleted
        $deleted = $this->nostoIndexCollectionFactory->create()->markAsDeleted($uniqueIds, $store);
        $this->getLogger()->info(
            sprintf(
                'Marked %d indexed products as deleted for store %s',
                $deleted,
                $store->getName()
            )
        );
    }

    /**
     * Loads (or reloads) Product object
     * @param int $productId
     * @param int $storeId
     * @return ProductInterface|Product|mixed
     * @throws NoSuchEntityException
     */
    private function loadMagentoProduct(int $productId, int $storeId)
    {
        return $this->productRepository->getById(
            $productId,
            false,
            $storeId,
            true
        );
    }
}
