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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Api\Data\ProductQueueSearchResultsInterface;
use Nosto\Tagging\Api\ProductQueueRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\Collection as QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\CollectionFactory as QueueCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Queue as QueueResource;

class QueueRepository implements ProductQueueRepositoryInterface
{
    private $queueResource;
    private $queueFactory;
    private $queueCollectionFactory;
    private $queueSearchResultsFactory;
    private $searchCriteriaBuilder;

    /**
     * QueueRepository constructor.
     *
     * @param QueueResource $queueResource
     * @param QueueFactory $queueFactory
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param QueueSearchResultsFactory $queueSearchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        QueueResource $queueResource,
        QueueFactory $queueFactory,
        QueueCollectionFactory $queueCollectionFactory,
        QueueSearchResultsFactory $queueSearchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder
    )
    {
        $this->queueResource = $queueResource;
        $this->queueFactory = $queueFactory;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->queueSearchResultsFactory = $queueSearchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritdoc
     */
    public function save(ProductQueueInterface $productQueue)
    {
        $existing = $this->getOneByProductId($productQueue->getProductId());
        if ($existing instanceof ProductQueueInterface
            && $existing->getId()
        ) {
            return $existing;
        }
        /** @noinspection PhpParamsInspection */
        $queue = $this->queueResource->save($productQueue);

        return $queue;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        /* @var $productQueue Queue */
        $productQueue = $this->queueFactory->create();
        $productQueue->getResource()->load($this->queueFactory->create(), $id);
        if (!$productQueue->getId()) {
            throw new NoSuchEntityException(__('Unable to find ProductQueue with ID "%1"', $id));
        }

        return $productQueue;
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductId($productId)
    {
        /* @var $productQueue Queue */
        $productQueue = $this->queueFactory->create();
        $productQueue->getResource()->load(
            $productQueue,
            $productId,
            ProductQueueInterface::PRODUCT_ID
        );

        return $productQueue;
    }

    /**
     * @inheritdoc
     */
    public function getByProductId($productId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductQueueInterface::PRODUCT_ID, $productId, 'eq')
            ->create();

        return $this->getList($searchCriteria);
    }

    /**
     * @inheritdoc
     */
    public function delete(ProductQueueInterface $productQueue)
    {
        /** @noinspection PhpParamsInspection */
        $this->queueResource->delete($productQueue);
    }


    /**
     * @inheritdoc
     */
    public function deleteByProductIds(array $ids)
    {
        foreach ($ids as $id) {
            $productQueue = $this->getByProductId($id);
            if ($productQueue instanceof ProductQueueSearchResultsInterface) {
                foreach ($productQueue->getItems() as $entry) {
                    $this->delete($entry);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /* @var QuoteCollection $collection */
        $collection = $this->queueFactory->create()->getCollection();
        /** @noinspection PhpParamsInspection */
        $this->addFiltersToCollection($searchCriteria, $collection);
        $collection->load();
        $searchResult = $this->queueSearchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * @inheritdoc
     */
    public function getAll()
    {
        $collection = $this->queueCollectionFactory->create();
        $collection->load();
        $searchResult = $this->queueSearchResultsFactory->create();
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * Adds filters to the collection
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param QueueCollection $collection
     */
    private function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, QueueCollection $collection)
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
