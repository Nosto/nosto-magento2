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

namespace Nosto\Tagging\Model\Product\Index;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Api\ProductIndexRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\Product\Index as IndexResource;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Magento\Store\Model\Store;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class IndexRepository implements ProductIndexRepositoryInterface
{
    /** @var IndexCollectionFactory  */
    private $indexCollectionFactory;

    /** @var IndexResource  */
    private $indexResource;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /**
     * IndexRepository constructor.
     *
     * @param IndexResource $indexResource
     * @param IndexCollectionFactory $indexCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     */
    public function __construct(
        IndexResource $indexResource,
        IndexCollectionFactory $indexCollectionFactory,
        TimezoneInterface $magentoTimeZone
    ) {
        $this->indexResource = $indexResource;
        $this->indexCollectionFactory = $indexCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductAndStore(ProductInterface $product, StoreInterface $store)
    {
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create()
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
        $collection = $this->indexCollectionFactory->create()
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
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create();
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
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create();
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
        $collection = $this->indexCollectionFactory->create()
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
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addStoreIdFilter($storeId)
            ->addProductIdFilter($productId)
            ->setPageSize(1)
            ->setCurPage(1);

        return $collection->getOneOrNull();
    }

    /**
     * Save product index entry
     *
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface|IndexResource
     * @throws Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function save(ProductIndexInterface $productIndex)
    {
        /** @noinspection PhpParamsInspection */
        /** @var AbstractModel $productIndex */
        return $this->indexResource->save($productIndex);
    }

    /**
     * Delete product index entry
     * @param ProductIndexInterface $productIndex
     * @throws Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function delete(ProductIndexInterface $productIndex)
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
        $collection = $this->indexCollectionFactory->create();
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Index::IN_SYNC => Index::DB_VALUE_BOOLEAN_TRUE,
                Index::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Index::PRODUCT_ID) => array_unique($productIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks products as deleted by given product ids and store
     *
     * @param array $ids
     * @param Store $store
     * @return int
     */
    public function markProductsAsDeleted(array $ids, Store $store)
    {
        $collection = $this->indexCollectionFactory->create();
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Index::IS_DELETED => Index::DB_VALUE_BOOLEAN_TRUE,
                Index::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Index::PRODUCT_ID) => array_unique($ids),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Deletes current indexed products in store
     *
     * @param IndexCollection $collection
     * @param Store $store
     * @return int
     */
    public function deleteCurrentItemsByStore(IndexCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Index $item */
        foreach ($collection->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $collection->getConnection();
        return $connection->delete(
            $collection->getMainTable(),
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as in_sync
     *
     * @param IndexCollection $collection
     * @param Store $store
     * @return int
     */
    public function markAsInSyncCurrentItemsByStore(IndexCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Index $item */
        foreach ($collection->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Index::IN_SYNC => Index::DB_VALUE_BOOLEAN_TRUE,
                Index::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
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
        $collection = $this->indexCollectionFactory->create();
        $connection = $collection->getConnection();
        return $connection->update(
            $collection->getMainTable(),
            [
                Index::IS_DIRTY => Index::DB_VALUE_BOOLEAN_TRUE,
                Index::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as dirty
     *
     * @param IndexCollection $collection
     * @param Store $store
     * @return int
     */
    public function markAsIsDirtyItemsByStore(IndexCollection $collection, Store $store)
    {
        $indexIds = [];
        /* @var Index $item */
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
                Index::IS_DIRTY => Index::DB_VALUE_BOOLEAN_TRUE,
                Index::UPDATED_AT => $this->magentoTimeZone->date()->format('Y-m-d H:i:s')
            ],
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * @param Index $product
     * @param Store $store
     * @throws Exception
     */
    public function updateProduct(Index $product, Store $store)
    {
        $product->setStore($store);
        $product->setIsDirty(false);
        $product->setUpdatedAt($this->magentoTimeZone->date());
        $this->save($product);
    }
}
