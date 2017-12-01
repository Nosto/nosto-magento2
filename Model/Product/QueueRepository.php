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
use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Api\Data\ProductQueueSearchResultsInterface;
use Nosto\Tagging\Api\ProductQueueRepositoryInterface;
use Nosto\Tagging\Model\AbstractBaseRepository;
use Nosto\Tagging\Model\RepositoryTrait;
use Nosto\Tagging\Model\ResourceModel\Product\Queue as QueueResource;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\Collection as QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Queue\CollectionFactory as QueueCollectionFactory;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class QueueRepository extends AbstractBaseRepository implements ProductQueueRepositoryInterface
{
    private $objectFactory;
    private $searchCriteriaBuilder;
    private $logger;

    /**
     * QueueRepository constructor.
     *
     * @param QueueResource $queueResource
     * @param QueueFactory $queueFactory
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param QueueSearchResultsFactory $queueSearchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param NostoLogger $logger
     */
    public function __construct(
        QueueResource $queueResource,
        QueueFactory $queueFactory,
        QueueCollectionFactory $queueCollectionFactory,
        QueueSearchResultsFactory $queueSearchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        NostoLogger $logger
    ) {
        parent::__construct(
            $queueResource
        );

        $this->setObjectCollectionFactory($queueCollectionFactory);
        $this->setObjectSearchResultsFactory($queueSearchResultsFactory);
        $this->objectFactory = $queueFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
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
        /** @var AbstractModel $productQueue */
        $queue = $this->getObjectResource()->save($productQueue);

        return $queue;
    }

    public function getIdentityKey()
    {
        return ProductQueueInterface::ID;
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductId($productId)
    {
        $results = $this->getByProductId($productId);
        foreach ($results->getItems() as $item) {
            return $item;
        }
    }

    /**
     * @inheritdoc
     */
    public function getByProductId($productId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductQueueInterface::PRODUCT_ID, $productId, 'eq')
            ->create();

        return $this->search($searchCriteria);
    }

    /**
     * @inheritdoc
     */
    public function delete(ProductQueueInterface $productQueue)
    {
        try {
            $this->getObjectResource()->delete($productQueue);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
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
                    $this->delete($entry); // @codingStandardsIgnoreLine
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstPage($pageSize)
    {
        /* @var QueueCollection $collection */
        $collection = $this->getObjectCollectionFactory()->create();
        $collection->setPageSize($pageSize);
        $collection->setCurPage(1);
        $collection->load();
        /* @var ProductQueueSearchResultsInterface $searchResults */
        $searchResults = $this->getObjectSearchResultsFactory()->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function getAll()
    {
        /* @var QueueCollection $collection */
        $collection = $this->getObjectCollectionFactory()->create();
        $collection->load();
        /* @var ProductQueueSearchResultsInterface $searchResults */
        $searchResults = $this->getObjectSearchResultsFactory()->create();
        $searchResults->setItems($this->objectCollection->getItems());
        $searchResults->setTotalCount($this->objectCollection->getSize());

        return $searchResults;
    }
}
