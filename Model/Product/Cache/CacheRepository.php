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

namespace Nosto\Tagging\Model\Product\Cache;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductCacheInterface;
use Nosto\Tagging\Api\ProductCacheRepositoryInterface;
use Nosto\Tagging\Model\Product\Cache;
use Nosto\Tagging\Model\ResourceModel\Product\Cache as CacheResource;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollectionFactory;
use Nosto\Tagging\Util\PagingIterator;

class CacheRepository implements ProductCacheRepositoryInterface
{
    const DELETE_PRODUCT_BATCH = 100;

    /** @var CacheCollectionFactory  */
    private $cacheCollectionFactory;

    /** @var CacheResource  */
    private $indexResource;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /**
     * IndexRepository constructor.
     *
     * @param CacheResource $cacheResource
     * @param CacheCollectionFactory $cacheCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     */
    public function __construct(
        CacheResource $cacheResource,
        CacheCollectionFactory $cacheCollectionFactory,
        TimezoneInterface $magentoTimeZone
    ) {
        $this->indexResource = $cacheResource;
        $this->cacheCollectionFactory = $cacheCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductAndStore(ProductInterface $product, StoreInterface $store)
    {
        /* @var CacheCollection $collection */
        $collection = $this->cacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addProductFilter($product)
            ->addStoreFilter($store)
            ->setPageSize(1)
            ->setCurPage(1);
        return $collection->getOneOrNull();
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $collection = $this->cacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIdFilter($id)
            ->setPageSize(1)
            ->setCurPage(1);
        return $collection->getOneOrNull();
    }

    /**
     * @inheritdoc
     */
    public function getTotalOutOfSync(Store $store)
    {
        /* @var CacheCollection $collection */
        $collection = $this->cacheCollectionFactory->create();
        $collection->addOutOfSyncFilter();
        if ((int)$store->getId() !== 0) {
            $collection->addStoreFilter($store);
        }
        return $collection->getSize();
    }

    /**
     * @inheritdoc
     */
    public function getTotalDirty(Store $store)
    {
        /* @var CacheCollection $collection */
        $collection = $this->cacheCollectionFactory->create();
        $collection->addIsDirtyFilter();
        if ((int)$store->getId() !== 0) {
            $collection->addStoreFilter($store);
        }
        return $collection->getSize();
    }
    /**
     * @inheritdoc
     */
    public function getByIds(array $ids)
    {
        $collection = $this->cacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIdsFilter($ids)
            ->setPageSize(1)
            ->setCurPage(1);
        return $collection->getItems(); // @codingStandardsIgnoreLine
    }

    /**
     * @inheritdoc
     */
    public function getByProductIdAndStoreId(int $productId, int $storeId)
    {
        /* @var CacheCollection $collection */
        $collection = $this->cacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addStoreIdFilter($storeId)
            ->addProductIdFilter($productId)
            ->setPageSize(1)
            ->setCurPage(1);

        return $collection->getOneOrNull();
    }

    /**
     * @param array $productIds
     * @param int $storeId
     * @return CacheCollection
     */
    public function getByProductIdsAndStoreId(array $productIds, int $storeId)
    {
        return $this->cacheCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addStoreIdFilter($storeId)
            ->addProductIdsFilter($productIds);
    }

    /**
     * Save product index entry
     *
     * @param ProductCacheInterface $productIndex
     * @return ProductCacheInterface|CacheResource
     * @throws Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function save(ProductCacheInterface $productIndex)
    {
        /** @noinspection PhpParamsInspection */
        /** @var AbstractModel $productIndex */
        return $this->indexResource->save($productIndex);
    }

    /**
     * Delete product index entry
     * @param ProductCacheInterface $productIndex
     * @throws Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function delete(ProductCacheInterface $productIndex)
    {
        /** @noinspection PhpParamsInspection */
        /** @var AbstractModel $productIndex */
        $this->indexResource->delete($productIndex);
    }

    /**
     * Marks products as deleted by given product ids and store
     *
     * @param array $productIds
     * @param Store $store
     * @return int
     */
    public function markAsInSync(array $productIds, Store $store)
    {
        $collection = $this->cacheCollectionFactory->create();
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Cache::IN_SYNC => Cache::DB_VALUE_BOOLEAN_TRUE,
                Cache::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Cache::PRODUCT_ID) => array_unique($productIds),
                sprintf('%s=?', Cache::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks products as deleted by given cached product collection
     *
     * @param CacheCollection $collection
     * @throws NostoException
     */
    public function markAsDeleted(CacheCollection $collection)
    {
        $collection->setPageSize(self::DELETE_PRODUCT_BATCH);
        $iterator = new PagingIterator($collection);
        foreach ($iterator as $page) {
            /** @var Cache $cachedProduct */
            foreach ($page as $cachedProduct) {
                $cachedProduct->setIsDeleted(true);
                $this->save($cachedProduct); // @codingStandardsIgnoreLine
            }
        }
    }

    /**
     * Deletes current indexed products in store
     *
     * @param CacheCollection $collection
     * @param Store $store
     * @return int number of deleted rows
     */
    public function deleteCurrentItemsByStore(CacheCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Cache $item */
        foreach ($collection->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $collection->getConnection();
        return $connection->delete(
            $collection->getMainTable(),
            [
                sprintf('%s IN (?)', Cache::ID) => array_unique($indexIds),
                sprintf('%s=?', Cache::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as in_sync
     *
     * @param CacheCollection $collection
     * @param Store $store
     * @return int
     */
    public function markAsInSyncCurrentItemsByStore(CacheCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Cache $item */
        foreach ($collection->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Cache::IN_SYNC => Cache::DB_VALUE_BOOLEAN_TRUE,
                Cache::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Cache::ID) => array_unique($indexIds),
                sprintf('%s=?', Cache::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks all products as dirty by given Store
     *
     * @param Store $store
     * @return int
     */
    public function markAllAsDirtyByStore(Store $store)
    {
        $collection = $this->cacheCollectionFactory->create();
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Cache::IS_DIRTY => Cache::DB_VALUE_BOOLEAN_TRUE,
                Cache::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s=?', Cache::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as dirty
     *
     * @param CacheCollection $collection
     * @param Store $store
     * @return int
     */
    public function markAsIsDirtyItemsByStore(CacheCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Cache $item */
        foreach ($collection->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        if (count($indexIds) <= 0) {
            return 0;
        }
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Cache::IS_DIRTY => Cache::DB_VALUE_BOOLEAN_TRUE,
                Cache::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Cache::ID) => array_unique($indexIds),
                sprintf('%s=?', Cache::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * @param Cache $product
     * @param StoreInterface $store
     * @throws Exception
     */
    public function updateProduct(Cache $product, StoreInterface $store)
    {
        $product->setStore($store);
        $product->setIsDirty(false);
        $product->setUpdatedAt($this->magentoTimeZone->date());
        $this->save($product);
    }
}
