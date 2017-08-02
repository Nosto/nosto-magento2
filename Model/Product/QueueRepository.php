<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Model\Product\QueueSearchResultsFactory;
use Nosto\Tagging\Api\ProductQueueRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\Product\QueueCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Queue as QueueResource;

class QueueRepository implements ProductQueueRepositoryInterface
{
    private $queueResource;
    private $queueFactory;
    private $queueCollectionFactory;
    private $queueSearchResultsFactory;

    /**
     * QueueRepository constructor.
     *
     * @param QueueResource $queueResource
     * @param QueueFactory $queueFactory
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param QueueSearchResultsFactory $queueSearchResultsFactory
     */
    public function __construct(
        QueueResource $queueResource,
        QueueFactory $queueFactory,
        QueueCollectionFactory $queueCollectionFactory,
        QueueSearchResultsFactory $queueSearchResultsFactory
    )
    {
        $this->queueResource = $queueResource;
        $this->queueFactory = $queueFactory;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->queueSearchResultsFactory = $queueSearchResultsFactory;
    }

    public function save(ProductQueueInterface $productQueue)
    {
        // ToDo - add check if product with same id already exists
        $existing = $this->getByProductId($productQueue->getProductId());
        if ($existing instanceof ProductQueueInterface) {
            return $existing;
        }
        $queue = $this->queueResource->save($productQueue);

        return $queue;
    }

    public function getById($id)
    {
        /* @var $productQueue Queue */
        $productQueue = $this->queueFactory->create();
        $productQueue->getResource()->load($this, $id);
        if (!$productQueue->getId()) {
            throw new NoSuchEntityException(__('Unable to find ProductQueue with ID "%1"', $id));
        }

        return $productQueue;
    }

    public function getByProductId($productId)
    {
        /* @var $productQueue Queue */
        $productQueue = $this->queueFactory->create();
        $productQueue->getResource()->load($this->queueFactory->create(), $productId, ProductQueueInterface::PRODUCT_ID);
        if (!$productQueue->getId()) {
            return null;
        }

        return $productQueue;
    }

    public function delete(ProductQueueInterface $productQueue)
    {
        $this->queueResource->delete($productQueue);
    }

    public function deleteById($id)
    {
        $productQueue = $this->getById($id);

        return $this->delete($productQueue);
    }

    public function deleteByProductIds(array $ids)
    {
        foreach ($ids as $id) {
            $productQueue = $this->getByProductId($id);
            if ($productQueue instanceof ProductQueueInterface) {
                $this->delete($productQueue);
            }
        }
    }

    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->queueFactory->create()->getCollection();

        $this->addFiltersToCollection($searchCriteria, $collection);
        $this->addSortOrdersToCollection($searchCriteria, $collection);
        $this->addPagingToCollection($searchCriteria, $collection);

        $collection->load();
        $searchResult = $this->queueSearchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    public function getAll()
    {
        $collection = $this->queueCollectionFactory->create();
        $collection->load();
        $searchResult = $this->queueSearchResultsFactory->create();
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    private function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, Collection $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $fields = $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $fields[] = $filter->getField();
                $conditions[] = [$filter->getConditionType() => $filter->getValue()];
            }
            $collection->addFieldToFilter($fields, $conditions);
        }
    }
}
