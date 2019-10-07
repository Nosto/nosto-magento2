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

namespace Nosto\Tagging\Model\ResourceModel\Product\Cache;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductCacheInterface;
use Nosto\Tagging\Model\Product\Cache;
use Nosto\Tagging\Model\ResourceModel\Product\Cache as CacheResource;

class CacheCollection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            Cache::class,
            CacheResource::class
        );
    }

    /**
     * @param StoreInterface $store
     * @return CacheCollection
     */
    public function addStoreFilter(StoreInterface $store)
    {
        return $this->addStoreIdFilter($store->getId());
    }

    /**
     * @param array $ids
     * @return CacheCollection
     */
    public function addIdsFilter(array $ids)
    {
        return $this->addFieldToFilter(
            Cache::ID,
            ['in' => $ids]
        );
    }

    /**
     * @param array $ids
     * @return CacheCollection
     */
    public function addProductIdsFilter(array $ids)
    {
        return $this->addFieldToFilter(
            Cache::PRODUCT_ID,
            ['in' => $ids]
        );
    }

    /**
     * Filters collection for items that are either dirty or out of sync with Nosto
     * @return CacheCollection
     */
    public function addOutOfSyncOrIsDirtyFilter()
    {
        return $this->addFieldToFilter(
            [ProductCacheInterface::IN_SYNC, ProductCacheInterface::IS_DIRTY],
            [Cache::DB_VALUE_BOOLEAN_FALSE, Cache::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Filters collection for items that are dirty
     *
     * @return CacheCollection
     */
    public function addIsDirtyFilter()
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::IS_DIRTY,
            ['eq' => Cache::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Filters collection by store id
     *
     * @param int $storeId
     * @return CacheCollection
     */
    public function addStoreIdFilter(int $storeId)
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::STORE_ID,
            ['eq' => $storeId]
        );
    }

    /**
     * Filters collection by product id
     *
     * @param int $productId
     * @return CacheCollection
     */
    public function addProductIdFilter(int $productId)
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::PRODUCT_ID,
            ['eq' => $productId]
        );
    }

    /**
     * Filters collection by product
     *
     * @param ProductInterface $product
     * @return CacheCollection
     */
    public function addProductFilter(ProductInterface $product)
    {
        return $this->addProductIdFilter($product->getId());
    }

    /**
     * Filters collection by id (primary key)
     *
     * @param int $indexId
     * @return CacheCollection
     */
    public function addIdFilter(int $indexId)
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::ID,
            ['eq' => $indexId]
        );
    }

    /**
     * Filters collection for items that out of sync
     *
     * @return CacheCollection
     */
    public function addOutOfSyncFilter()
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::IN_SYNC,
            ['eq' => Cache::DB_VALUE_BOOLEAN_FALSE]
        );
    }

    /**
     * Filters collection for only products that are not marked as deleted
     *
     * @return CacheCollection
     */
    public function addNotDeletedFilter()
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::IS_DELETED,
            ['eq' => Cache::DB_VALUE_BOOLEAN_FALSE]
        );
    }

    /**
     * Filters collection for only products that are not marked as deleted
     *
     * @return CacheCollection
     */
    public function addDeletedFilter()
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::IS_DELETED,
            ['eq' => Cache::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Filters collection for only products that are marked as deleted
     *
     * @return CacheCollection
     */
    public function addIsDeletedFilter()
    {
        return $this->addFieldToFilter(
            ProductCacheInterface::IS_DELETED,
            ['eq' => Cache::DB_VALUE_BOOLEAN_TRUE]
        );
    }

    /**
     * Returns the first item of the collection
     * or null if the collection is empty
     *
     * @return ProductCacheInterface|null
     * @suppress PhanTypeMismatchReturn
     */
    public function getOneOrNull()
    {
        $this->getSelect()->limit(1);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSize() > 0 ? $this->getLastItem() : null; // @codingStandardsIgnoreLine
    }

    /**
     * Sets a limit to this query
     *
     * @param int $limit
     * @return CacheCollection
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
     * @return CacheCollection
     */
    public function orderBy($field, $sort)
    {
        $this->getSelect()->order($field . ' ' . $sort);
        return $this;
    }
}
