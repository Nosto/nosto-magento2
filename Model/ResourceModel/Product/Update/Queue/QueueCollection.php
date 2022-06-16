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

use DateTimeInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Model\Product\Update\Queue;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue as QueueResource;

class QueueCollection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            Queue::class,
            QueueResource::class
        );
    }

    /**
     * @param StoreInterface $store
     * @return QueueCollection
     */
    public function addStoreFilter(StoreInterface $store): QueueCollection
    {
        return $this->addStoreIdFilter($store->getId());
    }

    /**
     * @param array $ids
     * @return QueueCollection
     */
    public function addIdsFilter(array $ids): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::ID,
            ['in' => $ids]
        );
    }

    /**
     * Filters collection by store id
     *
     * @param int $storeId
     * @return QueueCollection
     */
    public function addStoreIdFilter(int $storeId): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::STORE_ID,
            ['eq' => $storeId]
        );
    }

    /**
     * Filters collection by status
     *
     * @param string $status
     * @return QueueCollection
     */
    public function addStatusFilter(string $status): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::STATUS,
            ['eq' => $status]
        );
    }

    /**
     * Filters collection by completed by
     *
     * @param DateTimeInterface $dateTime
     * @return QueueCollection
     */
    public function addCompletedBeforeFilter(DateTimeInterface $dateTime): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::COMPLETED_AT,
            ['lteq' => $dateTime->format('Y-m-d H:i:s')]
        );
    }

    /**
     * Filters collection by action
     *
     * @param string $action
     * @return QueueCollection
     */
    public function addActionFilter(string $action): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::ACTION,
            ['eq' => $action]
        );
    }

    /**
     * Filters collection by id (primary key)
     *
     * @param int $indexId
     * @return QueueCollection
     */
    public function addIdFilter(int $indexId): QueueCollection
    {
        return $this->addFieldToFilter(
            ProductUpdateQueueInterface::ID,
            ['eq' => $indexId]
        );
    }

    /**
     * Sets a limit to this query
     *
     * @param int $limit
     * @return QueueCollection
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
     * @return QueueCollection
     */
    public function orderBy(string $field, string $sort)
    {
        $this->getSelect()->order($field . ' ' . $sort);
        return $this;
    }

    /**
     * Deserialize fields
     *
     * @return QueueCollection
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this->getItems() as $item) {
            /**
             * Argument is of type Magento\Framework\DataObject
             * but \Magento\Framework\Model\AbstractModel is expected
             */
            /** @phan-suppress-next-next-line PhanTypeMismatchArgumentSuperType */
            /** @noinspection PhpParamsInspection */
            $this->getResource()->unserializeFields($item);
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $item->setDataChanges(false);
        }
        return $this;
    }
}
