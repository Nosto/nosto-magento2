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

namespace Nosto\Tagging\Model\ResourceModel\Magento\Product;

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Sales\Api\Data\EntityInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as ProductCollectionFactory;

/**
 * A builder class for building product collection with the most common filters
 */
class CollectionBuilder
{
    /** @var ProductVisibility */
    private $productVisibility;

    /** @var Collection */
    private $collection;

    /** @var CollectionFactory */
    private $productCollectionFactory;

    /**
     * Collection constructor.
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductVisibility $productVisibility
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        ProductVisibility $productVisibility
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
    }

    /**
     * @return Collection
     */
    public function build()
    {
        return $this->collection;
    }

    /**
     * Sets filter for only products that are visible in active sites defined
     * by store
     * @return $this
     */
    public function withOnlyVisibleInSites()
    {
        $this->collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        return $this;
    }

    /**
     * Sets filter for only active products and globally visible products
     *
     * @return $this
     */
    public function withOnlyActiveAndVisible()
    {
        $this->collection->addActiveAndVisibleFilter();
        return $this;
    }

    /**
     * Sets the store filter
     *
     * @param StoreInterface $store
     * @return $this
     */
    public function withStore(StoreInterface $store)
    {
        $this->collection->addStoreFilter($store);
        $this->collection->setStore($store);
        return $this;
    }

    /**
     * Defines all attributes to be included into the collection items
     *
     * @return $this
     */
    public function withAllAttributes()
    {
        $this->collection->addAttributeToSelect('*');
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
        $this->collection->addIdsToFilter($ids);
        return $this;
    }

    /**
     * Sets the sort for the collection
     *
     * @param string $field
     * @param string $sortOrder
     * @return $this
     */
    public function setSort($field, $sortOrder)
    {
        $this->collection->setOrder($field, $sortOrder);
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
        $this->collection->setPageSize($pageSize);
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
        $this->collection->setCurPage($currentPage);
        return $this;
    }

    /**
     * Sets the default visibility. Calls self::withOnlyVisibleInSites()
     * and self::withOnlyActiveAndVisible()
     *
     * @return CollectionBuilder
     */
    public function withDefaultVisibility()
    {
        return $this->withOnlyVisibleInSites()->withOnlyActiveAndVisible();
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
        $this->collection = $this->productCollectionFactory->create();
        return $this;
    }

    /**
     * Initializes the collection with store filter and defaults
     *
     * @param StoreInterface $store
     * @return CollectionBuilder
     */
    public function initDefault(StoreInterface $store)
    {
        /** @var ProductCollection $collection */
        return $this
            ->reset()
            ->withStore($store)
            ->withAllAttributes()
            ->setSort(EntityInterface::CREATED_AT, $this->collection::SORT_ORDER_DESC);
    }

    /**
     * Builds and returns the collection with single item (if found)
     *
     * @param StoreInterface $store
     * @param $id
     * @return Collection
     */
    public function buildSingle(StoreInterface $store, $id)
    {
        return $this
            ->initDefault($store)
            ->withIds([$id])
            ->withDefaultVisibility()
            ->build();
    }

    /**
     * Builds collection with default visibility filter and given limit
     * and offset.
     *
     * @param StoreInterface $store
     * @param int $limit
     * @param int $offset
     * @return Collection
     */
    public function buildMany(StoreInterface $store, $limit = 100, $offset = 0)
    {
        $currentPage = ($offset / $limit) + 1;
        return $this
            ->initDefault($store)
            ->withDefaultVisibility()
            ->setPageSize($limit)
            ->setCurrentPage($currentPage)
            ->build();
    }
}
