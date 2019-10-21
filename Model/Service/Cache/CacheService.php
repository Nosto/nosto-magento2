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

namespace Nosto\Tagging\Model\Service\Cache;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductCacheInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Data as NostoIndexerData;
use Nosto\Tagging\Model\Indexer\Invalidate as NostoIndexerInvalidate;
use Nosto\Tagging\Model\Product\Cache as NostoProductIndex;
use Nosto\Tagging\Model\Product\Cache\CacheBuilder;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as ProductCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollectionFactory;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Product\ProductComparatorInterface;
use Nosto\Tagging\Model\Service\Product\ProductSerializerInterface;
use Nosto\Tagging\Model\Service\Product\ProductServiceInterface;
use Nosto\Tagging\Model\Service\Sync\Delete\AsyncBulkPublisher as ProductDeleteBulkPublisher;
use Nosto\Tagging\Model\Service\Sync\Upsert\AsyncBulkPublisher as ProductUpsertBulkPublisher;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class CacheService extends AbstractService
{
    const PRODUCT_DATA_BATCH_SIZE = 100;
    const PRODUCT_DELETION_BATCH_SIZE = 100;
    const BENCHMARK_BREAKPOINT_INVALIDATE = 100;
    const BENCHMARK_BREAKPOINT_REBUILD = 10;
    const BENCHMARK_NAME_INVALIDATE = 'nosto_index_invalidate';
    const BENCHMARK_NAME_REBUILD = 'nosto_index_rebuild';

    /** @var CacheRepository */
    private $cacheRepository;

    /** @var CacheBuilder */
    private $indexBuilder;

    /** @var ProductRepository */
    private $productRepository;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var CacheCollectionFactory */
    private $nostoCacheCollectionFactory;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /** @var NostoProductRepository $nostoProductRepository */
    private $nostoProductRepository;

    /** @var ProductCollectionFactory $productCollectionFactory */
    private $productCollectionFactory;

    /** @var array */
    private $invalidatedProducts = [];

    /** @var ProductUpsertBulkPublisher */
    private $productUpsertBulkPublisher;

    /** @var ProductDeleteBulkPublisher */
    private $productDeleteBulkPublisher;

    /** @var ProductSerializerInterface */
    private $productSerializer;

    /** @var ProductComparatorInterface */
    private $productComparator;

    /** @var ProductServiceInterface */
    private $productService;

    /**
     * CacheService constructor.
     * @param CacheRepository $cacheRepository
     * @param CacheBuilder $indexBuilder
     * @param ProductRepository $productRepository
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     * @param CacheCollectionFactory $nostoCacheCollectionFactory
     * @param NostoProductRepository $nostoProductRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     * @param NostoDataHelper $nostoDataHelper
     * @param ProductUpsertBulkPublisher $productUpsertBulkPublisher
     * @param ProductDeleteBulkPublisher $productDeleteBulkPublisher
     * @param ProductSerializerInterface $productSerializer
     * @param ProductComparatorInterface $productComparator
     * @param ProductServiceInterface $productService
     */
    public function __construct(
        CacheRepository $cacheRepository,
        CacheBuilder $indexBuilder,
        ProductRepository $productRepository,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger,
        CacheCollectionFactory $nostoCacheCollectionFactory,
        NostoProductRepository $nostoProductRepository,
        ProductCollectionFactory $productCollectionFactory,
        TimezoneInterface $magentoTimeZone,
        NostoDataHelper $nostoDataHelper,
        ProductUpsertBulkPublisher $productUpsertBulkPublisher,
        ProductDeleteBulkPublisher $productDeleteBulkPublisher,
        ProductSerializerInterface $productSerializer,
        ProductComparatorInterface $productComparator,
        ProductServiceInterface $productService
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->cacheRepository = $cacheRepository;
        $this->indexBuilder = $indexBuilder;
        $this->productRepository = $productRepository;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoCacheCollectionFactory = $nostoCacheCollectionFactory;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
        $this->productUpsertBulkPublisher = $productUpsertBulkPublisher;
        $this->productDeleteBulkPublisher = $productDeleteBulkPublisher;
        $this->productSerializer = $productSerializer;
        $this->productComparator = $productComparator;
        $this->productService = $productService;
    }

    /**
     * Handles only the first step of indexing
     * Create one if row does not exits
     * Else set row to dirty
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     * @throws Exception
     */
    public function invalidateOrCreate(ProductCollection $collection, Store $store)
    {
        $this->startBenchmark(
            self::BENCHMARK_NAME_INVALIDATE,
            self::BENCHMARK_BREAKPOINT_INVALIDATE
        );
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $this->checkMemoryConsumption('product invalidate');
            /** @var Product $item */
            foreach ($page->getItems() as $item) {
                $this->invalidateOrCreateProductOrParent($item, $store);
                $this->tickBenchmark(self::BENCHMARK_NAME_INVALIDATE);
            }
            $this->getLogger()->info(sprintf(
                '"%s" has processed by %d/%d for store "%s"',
                NostoIndexerInvalidate::INDEXER_ID,
                $iterator->getCurrentPageNumber(),
                $iterator->getLastPageNumber(),
                $store->getCode()
            ));
        }
        $this->logBenchmarkSummary(self::BENCHMARK_NAME_INVALIDATE, $store);
    }

    /**
     * @param Product $product
     * @param Store $store
     * @throws NostoException
     */
    public function invalidateOrCreateProductOrParent(Product $product, Store $store)
    {
        $parents = $this->nostoProductRepository->resolveParentProductIds($product);

        //Products has no parents and Index product itself
        if (empty($parents)) {
            $this->updateOrCreateDirtyEntity($product, $store);
            $this->invalidatedProducts[] = $product->getId();
            return;
        }

        //Loop through product parents and Index the parents
        if (is_array($parents)) {
            $this->invalidateOrCreateParents($parents, $store);
            return;
        }

        throw new NostoException('Could not index product with id: ' . $product->getId());
    }

    /**
     * @param array $ids
     * @param Store $store
     * @throws NostoException
     */
    private function invalidateOrCreateParents(array $ids, Store $store)
    {
        /** @var ProductCollection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addIdsToFilter($ids);
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            /** @var ProductInterface $product */
            foreach ($page->getItems() as $product) {
                if ($this->hasParentBeenInvalidated($product->getId()) === false) {
                    $this->updateOrCreateDirtyEntity($product, $store);
                    $this->invalidatedProducts[] = $product->getId();
                }
            }
        }
    }

    /**
     * @param $productId
     * @return bool
     */
    private function hasParentBeenInvalidated($productId)
    {
        if (in_array($productId, $this->invalidatedProducts, false)) {
            return true;
        }
        return false;
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return void
     */
    public function updateOrCreateDirtyEntity(ProductInterface $product, StoreInterface $store)
    {
        if (!$this->canBuildProduct($product)) {
            $this->getLogger()
                ->debug(sprintf('Product %s cannot be processed by Nosto', $product->getId()));
            return;
        }
        $indexedProduct = $this->cacheRepository->getByProductIdAndStoreId($product->getId(), $store->getId());
        try {
            if ($indexedProduct === null) { // Creates Index Product
                $indexedProduct = $this->indexBuilder->build($product, $store);
            }
            $indexedProduct->setIsDirty(true);
            $indexedProduct->setUpdatedAt($this->magentoTimeZone->date());
            $this->cacheRepository->save($indexedProduct);
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * Handle edge case when
     * 1. Bundle product has no options
     *
     * @param ProductInterface $product
     * @return bool
     */
    public function canBuildProduct(ProductInterface $product)
    {
        if ($product->getTypeId() === Type::TYPE_BUNDLE && empty($product->getOptions())) {
            return false;
        }
        return true;
    }

    /**
     * @param Store $store
     * @param array $ids
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     * @throws LocalizedException
     */
    public function generateProductsInStore(Store $store, array $ids = [])
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account === null) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $dirtyCollection = $this->getDirtyCollection($store, $ids);
        $this->rebuildDirtyProducts($dirtyCollection, $store);
        $outOfSyncCollection = $this->getOutOfSyncCollection($store, $ids);
        $this->productUpsertBulkPublisher->execute($outOfSyncCollection, $store);
        $deletedCollection = $this->getDeletedCollection($store);
        $this->productDeleteBulkPublisher->execute($deletedCollection, $store);
    }

    /**
     * @param CacheCollection $collection
     * @param Store $store
     * @throws Exception
     * @throws MemoryOutOfBoundsException
     */
    public function rebuildDirtyProducts(CacheCollection $collection, Store $store)
    {
        $this->startBenchmark(
            self::BENCHMARK_NAME_REBUILD,
            self::BENCHMARK_BREAKPOINT_REBUILD
        );
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var CacheCollection $page */
        foreach ($iterator as $page) {
            /** @var NostoProductIndex $item */
            foreach ($page->getItems() as $item) {
                $this->getLogger()->debug(
                    sprintf('Rebuilding product "%s"', $item->getProductId()),
                    ['store' => $item->getStoreId()]
                );
                $this->rebuildDirtyProduct($item);
                $this->tickBenchmark(self::BENCHMARK_NAME_REBUILD);
                $this->checkMemoryConsumption('product rebuild');
            }
            $this->getLogger()->info(sprintf(
                '"%s" has processed by %d/%d for store "%s"',
                NostoIndexerData::INDEXER_ID,
                $iterator->getCurrentPageNumber(),
                $iterator->getLastPageNumber(),
                $store->getCode()
            ));
        }

        $this->logBenchmarkSummary(self::BENCHMARK_NAME_REBUILD, $store);
    }

    /**
     * Rebuilds a dirty indexed product data & defines it as out of sync
     * if Nosto product data changed
     *
     * @param ProductCacheInterface $productIndex
     * @return ProductCacheInterface|null
     */
    public function rebuildDirtyProduct(ProductCacheInterface $productIndex)
    {
        try {
            /** @var Product $magentoProduct */
            $magentoProduct = $this->loadMagentoProduct(
                $productIndex->getProductId(),
                $productIndex->getStoreId()
            );
            $nostoProduct = $this->productService->getProduct(
                $magentoProduct,
                $this->nostoHelperScope->getStore($productIndex->getStoreId())
            );
            if ($nostoProduct === null) {
                $this->cacheRepository->delete($productIndex);
                return null;
            }
            $nostoCachedProduct = $this->productSerializer->fromString(
                $productIndex->getProductData()
            );
            if ($nostoCachedProduct instanceof NostoProductInterface === false ||
                (
                    $nostoProduct instanceof NostoProductInterface
                    && !$this->productComparator->isEqual($nostoProduct, $nostoCachedProduct)
                )
            ) {
                $productIndex->setProductData(
                    $this->productSerializer->toString(
                        $nostoProduct
                    )
                );
                $productIndex->setInSync(false);
                $this->getLogger()->debug(
                    sprintf(
                        'Saved dirty product "%d" for store "%d"',
                        $productIndex->getProductId(),
                        $productIndex->getStoreId()
                    )
                );
            }
            $productIndex->setIsDirty(false);
            $this->cacheRepository->save($productIndex);
            return $productIndex;
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
            return null;
        }
    }

    /**
     * Mark entries in the nosto indexer table as deleted
     * by checking the difference in ids between the collection
     * and the ones coming from cl table
     *
     * @param ProductCollection $collection
     * @param array $ids
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws Exception
     */
    public function markProductsAsDeletedByDiff(ProductCollection $collection, array $ids, Store $store)
    {
        $uniqueIds = array_unique($ids);
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            /** @var Product $product */
            $this->checkMemoryConsumption('mark product as deleted');
            foreach ($page->getItems() as $product) {
                $key = array_search($product->getId(), $uniqueIds, false);
                if (is_numeric($key)) {
                    unset($uniqueIds[$key]);
                }
            }
        }

        // Flag the rest of the ids as deleted
        $cachedCollection = $this->nostoCacheCollectionFactory->create()
            ->addProductIdsFilter($uniqueIds)
            ->addStoreFilter($store);
        $this->cacheRepository->markAsDeleted($cachedCollection);
        $this->getLogger()->info(
            sprintf(
                'Marked %d indexed products as deleted for store %s',
                $cachedCollection->getSize(),
                $store->getName()
            )
        );
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return CacheCollection
     */
    private function getDirtyCollection(Store $store, array $ids = [])
    {
        $collection = $this->nostoCacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIsDirtyFilter()
            ->addNotDeletedFilter()
            ->addStoreFilter($store);
        if (!empty($ids)) {
            $collection->addIdsFilter($ids);
        }
        return $collection;
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return CacheCollection
     */
    private function getOutOfSyncCollection(Store $store, array $ids = [])
    {
        $collection = $this->nostoCacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addOutOfSyncFilter()
            ->addNotDeletedFilter()
            ->addStoreFilter($store);
        if (!empty($ids)) {
            $collection->addIdsFilter($ids);
        }
        return $collection;
    }

    /**
     * @param Store $store
     * @return CacheCollection
     */
    private function getDeletedCollection(Store $store)
    {
        $collection = $this->nostoCacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIsDeletedFilter()
            ->addStoreFilter($store);
        return $collection;
    }

    /**
     * Loads (or reloads) Product object
     * @param int $productId
     * @param int $storeId
     * @return ProductInterface|Product
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
