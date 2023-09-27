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
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Magento\Framework\DB\Select;

/**
 * A builder class for building product collection with the most common filters
 */
class CollectionBuilder
{
    /** @var ProductCollection */
    private ProductCollection $productCollection;

    /** @var NostoHelperData */
    private NostoHelperData $nostoHelperData;

    /**
     * Collection constructor.
     * @param ProductCollection $productCollection,
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        ProductCollection $productCollection,
        NostoHelperData $nostoHelperData
    ) {
        $this->productCollection = $productCollection;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * @return Collection
     */
    public function build()
    {
        return $this->productCollection;
    }

    /**
     * Sets filter for only products that are visible in active sites defined
     * by store
     * @return $this
     */
    public function withOnlyVisibleInSites()
    {
        $this->productCollection->addAttributeToFilter(
            'visibility',
            ['neq' => ProductVisibility::VISIBILITY_NOT_VISIBLE]
        );
        return $this;
    }

    /**
     * Sets filter for product status based on configuration
     *
     * @param Store $store
     * @return $this
     */
    public function withConfiguredProductStatus(Store $store)
    {
        if (!$this->nostoHelperData->canIndexDisabledProducts($store)) {
            $this->productCollection->addActiveFilter();
        }
        return $this;
    }

    /**
     * Sets the store filter
     *
     * @param Store $store
     * @return $this
     */
    public function withStore(Store $store)
    {
        $this->productCollection->addStoreFilter($store);
        $this->productCollection->setStore($store);
        return $this;
    }

    /**
     * Defines all attributes to be included into the collection items
     *
     * @return $this
     */
    public function withAllAttributes()
    {
        $this->productCollection->addAttributeToSelect('*');
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
        $this->productCollection->addIdsToFilter($ids);
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
        $this->productCollection->setOrder($field, $sortOrder);
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
        $this->productCollection->setPageSize($pageSize);
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
        $this->productCollection->setCurPage($currentPage);
        return $this;
    }

    /**
     * Sets the default visibility and set active products filter based on configuration
     *
     * @param Store $store
     * @return CollectionBuilder
     */
    public function withDefaultVisibility(Store $store)
    {
        return $this->withOnlyVisibleInSites()->withConfiguredProductStatus($store);
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
        $this->productCollection->clear()->getSelect()->reset(Select::WHERE);
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
        /** @var ProductCollection $collection */
        return $this
            ->reset()
            ->withStore($store)
            ->setSort(EntityInterface::CREATED_AT, $this->productCollection::SORT_ORDER_DESC);
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
            ->withDefaultVisibility($store)
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
            ->withDefaultVisibility($store)
            ->setPageSize($limit)
            ->setCurrentPage($currentPage)
            ->build();
    }
}
