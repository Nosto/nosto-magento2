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

namespace Nosto\Tagging\Model\ResourceModel\Product\Index;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Model\Product\Index\Index;
use Nosto\Tagging\Model\ResourceModel\Product\Index as ResourceModelIndex;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            Index::class,
            ResourceModelIndex::class
        );
    }

    /**
     * @param StoreInterface $store
     * @return Collection
     */
    public function addStoreFilter(StoreInterface $store)
    {
        return $this->addStoreIdFilter($store->getId());
    }

    /**
     * @param array $ids
     * @return Collection
     */
    public function addIdsFilter(array $ids)
    {
        return $this->addFieldToFilter(
            Index::ID,
            ['in' => $ids]
        );
    }

    /**
     * Filters collection for items that are either dirty or out of sync with Nosto
     * @return Collection
     */
    public function addOutOfSyncOrIsDirtyFilter()
    {
        return $this->addFieldToFilter(
            [ProductIndexInterface::IN_SYNC, ProductIndexInterface::IS_DIRTY],
            [Index::DB_VALUE_BOOLEAN_FALSE, Index::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Filters collection for items that are dirty
     *
     * @return Collection
     */
    public function addIsDirtyFilter()
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::IS_DIRTY,
            ['eq' => Index::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Filters collection by store id
     *
     * @param int $storeId
     * @return Collection
     */
    public function addStoreIdFilter(int $storeId)
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::STORE_ID,
            ['eq' => $storeId]
        );
    }

    /**
     * Filters collection by product id
     *
     * @param int $productId
     * @return Collection
     */
    public function addProductIdFilter(int $productId)
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::PRODUCT_ID,
            ['eq' => $productId]
        );
    }

    /**
     * Filters collection by product
     *
     * @param ProductInterface $product
     * @return Collection
     */
    public function addProductFilter(ProductInterface $product)
    {
        return $this->addProductIdFilter($product->getId());
    }

    /**
     * Filters collection by id (primary key)
     *
     * @param int $indexId
     * @return Collection
     */
    public function addIdFilter(int $indexId)
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::ID,
            ['eq' => $indexId]
        );
    }

    /**
     * Filters collection for items that out of sync
     *
     * @return Collection
     */
    public function addOutOfSyncFilter()
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::IN_SYNC,
            ['eq' => Index::DB_VALUE_BOOLEAN_FALSE]
        );
    }

    /**
     * Filters collection for only products that are not marked as deleted
     *
     * @return Collection
     */
    public function addNotDeletedFilter()
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::IS_DELETED,
            ['eq' => Index::DB_VALUE_BOOLEAN_FALSE]
        );
    }

    /**
     * Filters collection for only products that are marked as deleted
     *
     * @return Collection
     */
    public function addIsDeletedFilter()
    {
        return $this->addFieldToFilter(
            ProductIndexInterface::IS_DELETED,
            ['eq' => Index::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Returns the first item of the collection
     * or null if the collection is empty
     *
     * @return ProductIndexInterface|null
     * @suppress PhanTypeMismatchReturn
     */
    public function getOneOrNull()
    {
        $this->getSelect()->limit(1);
        return $this->getSize() > 0 ? $this->getLastItem() : null; // @codingStandardsIgnoreLine
    }

    /**
     * Marks products as deleted by given product ids and store
     *
     * @param array $ids
     * @param Store $store
     * @return int
     */
    public function markAsDeleted(array $ids, Store $store)
    {
        if (empty($ids)) {
            return 0;
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IS_DELETED => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::PRODUCT_ID) => array_unique($ids),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks products as deleted by given product ids and store
     *
     * @param array $productIds array of product ids
     * @param Store $store
     * @return int
     */
    public function markAsInSync(array $productIds, Store $store)
    {
        if (empty($productIds)) {
            return 0;
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IN_SYNC => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::PRODUCT_ID) => array_unique($productIds),
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
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IS_DIRTY => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Deletes current indexed products in store
     *
     * @param Store $store
     * @return int
     */
    public function deleteCurrentItemsByStore(Store $store)
    {
        if ($this->getSize() === 0) {
            return 0;
        }
        $indexIds = [];
        /* @var Index $item */
        foreach ($this->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $this->getConnection();
        return $connection->delete(
            $this->getMainTable(),
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as in_sync
     *
     * @param Store $store
     * @return int
     */
    public function markAsInSyncCurrentItemsByStore(Store $store)
    {
        if ($this->getSize() === 0) {
            return 0;
        }
        $indexIds = [];
        /* @var Index $item */
        foreach ($this->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IN_SYNC => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as dirty
     *
     * @param Store $store
     * @return int
     */
    public function markAsIsDirtyItemsByStore(Store $store)
    {
        $indexIds = [];
        /* @var Index $item */
        foreach ($this->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        if (count($indexIds) <= 0 ) {
            return 0;
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IS_DIRTY => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Sets a limit to this query
     *
     * @param int $limit
     * @return Collection
     */
    public function limitResults(int $limit)
    {
        $this->getSelect()->limit($limit);
        return $this;
    }

    /**
     * Add sortby to query
     *
     * @param string $field
     * @param string $sort
     * @return Collection
     */
    public function orderBy($field, $sort)
    {
        $this->getSelect()->order($field . ' ' . $sort);
        return $this;
    }
}
