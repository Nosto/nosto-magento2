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

namespace Nosto\Tagging\Model\Service\Indexer;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Data as NostoIndexerData;
use Nosto\Tagging\Model\Indexer\Invalidate as NostoIndexerInvalidate;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\Index\Builder;
use Nosto\Tagging\Model\Product\Index\Index as NostoProductIndex;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as ProductCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as NostoIndexCollectionFactory;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Comparator\ProductComparatorInterface;
use Nosto\Tagging\Model\Service\Sync\BulkSyncInterface;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Util\Serializer\ProductSerializer;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class IndexService extends AbstractService
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

    /** @var NostoProductRepository $nostoProductRepository */
    private $nostoProductRepository;

    /** @var ProductCollectionFactory $productCollectionFactory */
    private $productCollectionFactory;

    /** @var array */
    private $invalidatedProducts = [];

    /** @var BulkSyncInterface */
    private $syncBulkPublisher;

    /** @var ProductSerializer */
    private $productSerializer;

    /** @var ProductComparatorInterface */
    private $productComparator;

    /**
     * Index constructor.
     * @param IndexRepository $indexRepository
     * @param Builder $indexBuilder
     * @param ProductRepository $productRepository
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     * @param NostoIndexCollectionFactory $nostoIndexCollectionFactory
     * @param NostoProductRepository $nostoProductRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     * @param NostoDataHelper $nostoDataHelper
     * @param BulkSyncInterface $syncBulkPublisher
     * @param ProductSerializer $productSerializer
     * @param ProductComparatorInterface $productComparator
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
        NostoProductRepository $nostoProductRepository,
        ProductCollectionFactory $productCollectionFactory,
        TimezoneInterface $magentoTimeZone,
        NostoDataHelper $nostoDataHelper,
        BulkSyncInterface $syncBulkPublisher,
        ProductSerializer $productSerializer,
        ProductComparatorInterface $productComparator
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->indexRepository = $indexRepository;
        $this->indexBuilder = $indexBuilder;
        $this->productRepository = $productRepository;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoIndexCollectionFactory = $nostoIndexCollectionFactory;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
        $this->syncBulkPublisher = $syncBulkPublisher;
        $this->productSerializer = $productSerializer;
        $this->productComparator = $productComparator;
    }

    /**
     * Handles only the first step of indexing
     * Create one if row does not exits
     * Else set row to dirty
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
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
        if (!$this->canBuildBundleProduct($product)) {
            $this->getLogger()
                ->debug(sprintf('Product %s cannot be processed by Nosto', $product->getId()));
            return;
        }
        $indexedProduct = $this->indexRepository->getByProductIdAndStoreId($product->getId(), $store->getId());
        try {
            if ($indexedProduct === null) { // Creates Index Product
                $indexedProduct = $this->indexBuilder->build($product, $store);
            }
            $indexedProduct->setIsDirty(true);
            $indexedProduct->setUpdatedAt($this->magentoTimeZone->date());
            $this->indexRepository->save($indexedProduct);
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
    public function canBuildBundleProduct(ProductInterface $product)
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
     */
    public function indexProducts(Store $store, array $ids = [])
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account === null) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $dirtyCollection = $this->getDirtyCollection($store, $ids);
        $this->rebuildDirtyProducts($dirtyCollection, $store);
        $outOfSyncCollection = $this->getOutOfSyncCollection($store, $ids);
        $this->syncBulkPublisher->execute($outOfSyncCollection, $store);
    }

    /**
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws Exception
     * @throws MemoryOutOfBoundsException
     */
    public function rebuildDirtyProducts(NostoIndexCollection $collection, Store $store)
    {
        $this->startBenchmark(
            self::BENCHMARK_NAME_REBUILD,
            self::BENCHMARK_BREAKPOINT_REBUILD
        );
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var NostoIndexCollection $page */
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
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface|null
     */
    public function rebuildDirtyProduct(ProductIndexInterface $productIndex)
    {
        try {
            /** @var Product $magentoProduct */
            $magentoProduct = $this->loadMagentoProduct(
                $productIndex->getProductId(),
                $productIndex->getStoreId()
            );
            $store = $this->nostoHelperScope->getStore($productIndex->getStoreId());
            $nostoProduct = $this->nostoProductBuilder->build($magentoProduct, $store);
            $nostoIndexedProduct = $this->productSerializer->fromString(
                $productIndex->getProductData()
            );
            if ($nostoIndexedProduct instanceof NostoProductInterface === false ||
                (
                    $nostoProduct instanceof NostoProductInterface
                    && !$this->productComparator->isEqual($nostoProduct, $nostoIndexedProduct)
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
            $this->indexRepository->save($productIndex);
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
     */
    public function markProductsAsDeletedByDiff(ProductCollection $collection, array $ids, Store $store)
    {
        $uniqueIds = array_unique($ids);
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            /** @var Product $product */
            foreach ($page->getItems() as $product) {
                $key = array_search($product->getId(), $uniqueIds, false);
                if (is_numeric($key)) {
                    unset($uniqueIds[$key]);
                }
            }
        }

        // Flag the rest of the ids as deleted
        $deleted = $this->indexRepository->markProductsAsDeleted($uniqueIds, $store);
        $this->getLogger()->info(
            sprintf(
                'Marked %d indexed products as deleted for store %s',
                $deleted,
                $store->getName()
            )
        );
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return NostoIndexCollection
     */
    private function getDirtyCollection(Store $store, array $ids = [])
    {
        $collection = $this->nostoIndexCollectionFactory->create()
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
     * @return NostoIndexCollection
     */
    private function getOutOfSyncCollection(Store $store, array $ids = [])
    {
        $collection = $this->nostoIndexCollectionFactory->create()
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
