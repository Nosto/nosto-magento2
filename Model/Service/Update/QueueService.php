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

namespace Nosto\Tagging\Model\Service\Update;

use Exception;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductCacheInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Cache as NostoProductIndex;
use Nosto\Tagging\Model\Product\Cache\CacheBuilder;
use Nosto\Tagging\Model\Product\Queue\QueueBuilder;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
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

/**
 * Class QueueService
 */
class QueueService extends AbstractService
{
    const PRODUCTID_BATCH_SIZE = 1000;
    const PRODUCT_DELETION_BATCH_SIZE = 1000;

    /** @var QueueRepository  */
    private $queueRepository;

    /** @var QueueBuilder */
    private $queueBuilder;

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
     * @param QueueRepository $queueRepository
     * @param CacheBuilder $queueBuilder
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
        QueueRepository $queueRepository,
        QueueBuilder $queueBuilder,
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
        $this->queueRepository = $queueRepository;
        $this->queueBuilder = $queueBuilder;
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
     * Sets the products into the update queue
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws Exception
     */
    public function addCollectionToQueue(ProductCollection $collection, Store $store)
    {
        $collection->setPageSize(self::PRODUCTID_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $queueEntry = $this->queueBuilder->build(
                $store,
                $this->toParentProructIds($page)
            );
            if (count($queueEntry->getProductIds()) > 0) {
                $this->queueRepository->save($queueEntry);
            }
        }
    }

    /**
     * @param ProductCollection $collection
     * @return array
     * @throws NostoException
     */
    private function toParentProructIds(ProductCollection $collection)
    {
        $productIds = [];
        /** @var ProductCollection $collection */
        $collection->setPageSize(self::PRODUCTID_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            /** @var ProductInterface $product */
            foreach ($page->getItems() as $product) {
                $parents = $this->nostoProductRepository->resolveParentProductIds($product);
                if (!empty($parents)) {
                    foreach ($parents as $id) {
                        $productIds[] = $id;
                    }
                } else {
                    $productIds[] = $product->getId();
                }
            }
        }
        return array_unique($productIds);
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
     * Handle edge case when
     * 1. Bundle product has no options
     *
     * @param ProductInterface $product
     * @return bool
     */
    public function canBuildProduct(ProductInterface $product)
    {
        if ($product->getTypeId() === Type::TYPE_BUNDLE && $this->hasBundleProductOptions($product)) {
            return false;
        }
        return true;
    }

    /**
     * @param ProductInterface $product
     * @return bool
     * @suppress PhanUndeclaredMethod
     */
    private function hasBundleProductOptions(ProductInterface $product)
    {
        /** @var BundleType $typeInstance */
        $typeInstance = $product->getTypeInstance();
        return empty($typeInstance->getOptionsIds($product));
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
                $this->queueRepository->delete($productIndex);
                return null;
            }
            try {
                $nostoCachedProduct = $this->productSerializer->fromString(
                    $productIndex->getProductData()
                );
            } catch (\Exception $e) {
                $this->getLogger()->exception($e);
                $nostoCachedProduct = null;
            }
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
            $this->queueRepository->save($productIndex);
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
        $this->queueRepository->markAsDeleted($cachedCollection);
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
