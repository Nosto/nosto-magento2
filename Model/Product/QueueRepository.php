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

namespace Nosto\Tagging\Model\Product;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Api\Data\ProductQueueSearchResultsInterface;
use Nosto\Tagging\Api\ProductQueueRepositoryInterface;
use Nosto\Tagging\Model\RepositoryTrait;
use Nosto\Tagging\Model\ResourceModel\Product\Queue as QueueResource;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\Collection as QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\CollectionFactory as QueueCollectionFactory;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Util\Repository as RepositoryUtil;
use /** @noinspection PhpUnusedAliasInspection */
    Nosto\Tagging\Model\Product\QueueSearchResults;
use Magento\Framework\Api\Search\SearchResult;

class QueueRepository implements ProductQueueRepositoryInterface
{
    private $searchCriteriaBuilder;
    private $logger;
    private $queueCollectionFactory;
    private $queueSearchResultsFactory;
    private $queueResource;

    /**
     * QueueRepository constructor.
     *
     * @param QueueResource $queueResource
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param QueueSearchResultsFactory $queueSearchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param NostoLogger $logger
     */
    public function __construct(
        QueueResource $queueResource,
        QueueCollectionFactory $queueCollectionFactory,
        QueueSearchResultsFactory $queueSearchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        NostoLogger $logger
    ) {
        $this->queueResource = $queueResource;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->queueSearchResultsFactory = $queueSearchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Save Queue entry
     *
     * @param ProductQueueInterface $productQueue
     * @return ProductQueueInterface|QueueResource
     * @throws \Exception
     * @suppress PhanTypeMismatchArgument
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
        /** @var AbstractModel $productQueue */
        return $this->queueResource->save($productQueue);
    }

    /**
     * Returns single entry by product id
     *
     * @param int $productId
     * @return ProductQueueInterface|null
     */
    public function getOneByProductId($productId)
    {
        $results = $this->getByProductId($productId);
        foreach ($results->getItems() as $item) {
            return $item;
        }

        return null;
    }

    /**
     * Returns all entries by product ids
     *
     * @param int $productId
     *
     * @return QueueSearchResults
     */
    public function getByProductId($productId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductQueueInterface::PRODUCT_ID, $productId, 'eq')
            ->create();

        return $this->search($searchCriteria);
    }

    /**
     * Delete productQueue
     *
     * @param ProductQueueInterface $productQueue
     *
     * @suppress PhanTypeMismatchArgument
     */
    public function delete(ProductQueueInterface $productQueue)
    {
        try {
            /** @noinspection PhpParamsInspection */
            $this->queueResource->delete($productQueue);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param array $ids
     */
    public function deleteByProductIds(array $ids)
    {
        foreach ($ids as $id) {
            $productQueue = $this->getByProductId($id);
            if ($productQueue instanceof ProductQueueSearchResultsInterface) {
                foreach ($productQueue->getItems() as $entry) {
                    $this->delete($entry); // @codingStandardsIgnoreLine
                }
            }
        }
    }

    /**
     * Get list of productQueues
     *
     * @param int $pageSize
     *
     * @return QueueSearchResults
     */
    public function getFirstPage($pageSize)
    {
        /* @var QueueCollection $collection */
        $collection = $this->queueCollectionFactory->create();
        $collection->setPageSize($pageSize);
        $collection->setCurPage(1);
        $collection->load();
        /* @var QueueSearchResults $searchResults */
        $searchResults = $this->queueSearchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        // Set collection to be mem dealloc
        $collection->clear();

        return $searchResults;
    }

    /**
     * Returns all entries in product queue
     *
     * @return QueueSearchResults
     */
    public function getAll()
    {
        $collection = $this->queueCollectionFactory->create();
        $collection->load();
        $searchResults = $this->queueSearchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResult
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->queueCollectionFactory->create();
        $searchResults = $this->queueSearchResultsFactory->create();

        return RepositoryUtil::search(
            $collection,
            $searchCriteria,
            $searchResults
        );
    }

    /**
     * Returns if nosto_queue has rows
     *
     * @return bool
     */
    public function isQueuePopulated()
    {
        $collection = $this->queueCollectionFactory->create();
        return $collection->getSize() > 0;
    }

    /**
     * Truncate productQueue table
     */
    public function truncate()
    {
        try {
            /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
            $connection = $this->queueResource->getConnection();
            $tableName = $this->queueResource->getMainTable();
            $connection->truncateTable($tableName);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }
}
