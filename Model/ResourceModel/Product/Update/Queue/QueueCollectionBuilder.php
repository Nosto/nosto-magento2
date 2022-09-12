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

namespace Nosto\Tagging\Model\ResourceModel\Product\Update\Queue;

use DateInterval;
use DateTime;
use Exception;
use Magento\Sales\Api\Data\EntityInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;

/**
 * A builder class for building update queue collection with the most common filters
 */
class QueueCollectionBuilder
{
    /** @var QueueCollection */
    private QueueCollection $queueCollection;

    /**
     * Collection constructor.
     * @param QueueCollection $queueCollection
     */
    public function __construct(
        QueueCollection $queueCollection
    ) {
        $this->queueCollection = $queueCollection;
    }

    /**
     * @return QueueCollection
     */
    public function build(): QueueCollection
    {
        return $this->queueCollection;
    }

    /**
     * Sets the store filter
     *
     * @param Store $store
     * @return $this
     */
    public function withStore(Store $store)
    {
        $this->queueCollection->addStoreFilter($store);
        return $this;
    }

    /**
     * Sets the filter to only done (completed) queue entries
     *
     * @return $this
     */
    public function withStatusNew()
    {
        $this->queueCollection->addStatusFilter(ProductUpdateQueueInterface::STATUS_VALUE_NEW);
        return $this;
    }

    /**
     * Sets the filter to only entries completed before given date time
     *
     * @param int $hrs
     * @return $this
     * @throws Exception
     */
    public function withCompletedHrsAgo(int $hrs)
    {
        $date = new DateTime('now');
        $interval = new DateInterval('PT' . $hrs . 'H');
        $date->sub($interval);
        $this->queueCollection->addCompletedBeforeFilter($date);
        return $this->withStatusCompleted();
    }

    /**
     * Sets the filter to only new (unprocessed)
     *
     * @return $this
     */
    public function withStatusCompleted()
    {
        $this->queueCollection->addStatusFilter(ProductUpdateQueueInterface::STATUS_VALUE_DONE);
        return $this;
    }

    /**
     * Sets the filter to only for given ids
     *
     * @param array $ids
     * @return $this
     */
    public function withIds(array $ids)
    {
        $this->queueCollection->addIdsFilter($ids);
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
        $this->queueCollection->setOrder($field, $sortOrder);
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
        $this->queueCollection->setPageSize($pageSize);
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
        $this->queueCollection->setCurPage($currentPage);
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
        $this->queueCollection->resetData();
        return $this;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function distinct(bool $flag)
    {
        $this->queueCollection->distinct($flag);
        return $this;
    }

    /**
     * Initializes the collection with store filter and defaults
     *
     * @param Store $store
     * @return QueueCollectionBuilder
     */
    public function initDefault(Store $store)
    {
        /** @var QueueCollection $queueCollection */
        return $this
            ->reset()
            ->withStore($store)
            ->setSort(EntityInterface::CREATED_AT, $this->queueCollection::SORT_ORDER_ASC)
            ->distinct(true);
    }
}
