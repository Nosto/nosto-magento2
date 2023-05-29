<?php
/*
 * Copyright (c) 2023, Nosto Solutions Ltd
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

namespace Nosto\Tagging\Model\ResourceModel\Magento\Category;

use Magento\Sales\Api\Data\EntityInterface;
use Magento\Store\Model\Store;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Zend_Db_Select;

/**
 * A builder class for building product collection with the most common filters
 */
class CollectionBuilder
{
    /** @var CategoryCollection */
    private CategoryCollection $categoryCollection;

    /**
     * Collection constructor.
     * @param CategoryCollection $categoryCollection,
     */
    public function __construct(
        CategoryCollection $categoryCollection
    ) {
        $this->categoryCollection = $categoryCollection;
    }

    /**
     * @return CategoryCollection
     */
    public function build()
    {
        return $this->categoryCollection;
    }

    /**
     * Sets the store filter
     *
     * @param Store $store
     * @return $this
     */
    public function withStore(Store $store)
    {
        $this->categoryCollection->setProductStoreId($store->getId());
        $this->categoryCollection->setStore($store);
        return $this;
    }

    /**
     * Defines all attributes to be included into the collection items
     *
     * @return $this
     */
    public function withAllAttributes()
    {
        $this->categoryCollection->addAttributeToSelect('*');
        return $this;
    }

    /**
     * Sets filter for only given product ids
     *
     * @param array $ids
     * @return $this
     */
    public function withIds(array $ids)
    {
        $this->categoryCollection->addIdFilter($ids);
        return $this;
    }

    /**
     * Sets the sort for the collection
     *
     * @param string $field
     * @param string $sortOrder
     * @return $this
     */
    public function setSort(string $field, string $sortOrder)
    {
        $this->categoryCollection->setOrder($field, $sortOrder);
        return $this;
    }

    /**
     * Sets the page size
     *
     * @param $pageSize
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        $this->categoryCollection->setPageSize($pageSize);
        return $this;
    }

    /**
     * Sets the current page
     *
     * @param $currentPage
     * @return $this
     */
    public function setCurrentPage($currentPage)
    {
        $this->categoryCollection->setCurPage($currentPage);
        return $this;
    }

    /**
     * Resets the data and filters in collection
     * @return $this
     */
    public function reset()
    {
        return $this->init();
    }

    /**
     * Initializes the collection
     *
     * @return $this
     */
    public function init()
    {
        $this->categoryCollection->clear()->getSelect()->reset(Zend_Db_Select::WHERE);
        return $this;
    }

    /**
     * Initializes the collection with store filter and defaults
     *
     * @param Store $store
     * @return CollectionBuilder
     */
    public function initDefault(Store $store)
    {
        /** @var CategoryCollection $collection */
        return $this
            ->reset()
            ->withStore($store)
            ->setSort(EntityInterface::CREATED_AT, $this->categoryCollection::SORT_ORDER_DESC);
    }

    /**
     * Builds and returns the collection with single item (if found)
     *
     * @param Store $store
     * @param $id
     * @return Collection
     */
    public function buildSingle(Store $store, $id)
    {
        return $this
            ->initDefault($store)
            ->withIds([$id])
            ->build();
    }

    /**
     * Builds collection with default visibility filter and given limit
     * and offset.
     *
     * @param Store $store
     * @param int $limit
     * @param int $offset
     * @return Collection
     */
    public function buildMany(Store $store, int $limit = 100, int $offset = 0)
    {
        $currentPage = ($offset / $limit) + 1;
        return $this
            ->initDefault($store)
            ->setPageSize($limit)
            ->setCurrentPage($currentPage)
            ->build();
    }
}
