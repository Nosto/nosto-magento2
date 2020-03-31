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

namespace Nosto\Tagging\Model\Service\Update;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollectionBuilder;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;
use Nosto\Tagging\Util\PagingIterator;


/**
 * Class QueueService
 */
class QueueProcessorService extends AbstractService
{
    const CLEANUP_INTERVAL_HRS = 4;

    /** @var BulkPublisherInterface */
    private $upsertBulkPublisher;

    /** @var QueueRepository */
    private $queueRepository;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /** @var QueueCollectionBuilder */
    private $queueCollectionBuilder;

    /** @var BulkPublisherInterface */
    private $deleteBulkPublisher;

    /** @var CollectionBuilder */
    private $productCollectionBuilder;

    /** @var Scope */
    private $scopeHelper;

    /**
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param BulkPublisherInterface $upsertBulkPublisher
     * @param BulkPublisherInterface $deleteBulkPublisher
     * @param QueueRepository $queueRepository
     * @param TimezoneInterface $magentoTimeZone
     * @param QueueCollectionBuilder $queueCollectionBuilder
     * @param CollectionBuilder $productCollectionBuilder
     */
    public function __construct(
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        BulkPublisherInterface $upsertBulkPublisher,
        BulkPublisherInterface $deleteBulkPublisher,
        QueueRepository $queueRepository,
        TimezoneInterface $magentoTimeZone,
        QueueCollectionBuilder $queueCollectionBuilder,
        CollectionBuilder $productCollectionBuilder,
        Scope $scopeHelper
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->upsertBulkPublisher = $upsertBulkPublisher;
        $this->deleteBulkPublisher = $deleteBulkPublisher;
        $this->queueRepository = $queueRepository;
        $this->magentoTimeZone = $magentoTimeZone;
        $this->queueCollectionBuilder = $queueCollectionBuilder;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->scopeHelper = $scopeHelper;
    }

    /**
     * Processes a collection of queue entries
     * - merges product ids from queue entries within the same store
     * @param QueueCollection $collection
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function processQueueCollection(QueueCollection $collection)
    {
        $this->notifyStartProcessing($collection);
        $merged = $this->mergeQueues($collection);
        foreach ($merged as $storeId => $productIds) {
            $deleted = $this->findDeletedProducts($productIds, $storeId);
            $this->upsertBulkPublisher->execute($storeId, array_diff($productIds, $deleted));
            $this->deleteBulkPublisher->execute($storeId, $deleted);
        }
        $this->notifyEndProcessing($collection);
    }

    /**
     * Merges productIds from QueueCollection into an array containing only unique product ids per store
     *
     * @param QueueCollection $collection
     * @return array
     */
    private function mergeQueues(QueueCollection $collection)
    {
        $merged = [];
        /* @var ProductUpdateQueueInterface $queueEntry */
        foreach ($collection as $queueEntry) {
            if (!isset($merged[$queueEntry->getStoreId()])) {
                $merged[$queueEntry->getStoreId()] = [];
            }
            foreach ($queueEntry->getProductIds() as $productId) {
                $merged[$queueEntry->getStoreId()][$productId] = $productId;
            }
        }
        return $merged;
    }

    /**
     * Returns the product ids that are no longer present in the db
     *
     * @param array $productIds
     * @return array
     */
    private function findDeletedProducts(array $productIds, $storeId)
    {
        $present = [];
        $removed = [];
        //TODO - needs to be in paginated collection to avoid memory hogging
        $store = $this->scopeHelper->getStore($storeId);
        $collection = $this->productCollectionBuilder->initDefault($store)
            ->withIds($productIds)
            ->build();
        $collection->setPageSize(100);
        try {
            $iterator = new PagingIterator($collection);
        } catch (NostoException $e) {
            $this->getLogger()->exception($e);
            return [];
        }
        foreach ($iterator as $page) {
            foreach ($page->getItems() as $item) {
                $id = $item->getId();
                $present[$id] = $id;
            }
        }
        foreach ($productIds as $productId) {
            if (!isset($present[$productId])) {
                $removed[] = $productId;
            }
        }
        return $removed;
    }

    /**
     * Sets the timestamp for started at & updates the status to be processing
     *
     * @param QueueCollection $collection
     */
    private function notifyStartProcessing(QueueCollection $collection)
    {
        /* @var ProductUpdateQueueInterface $queueEntry */
        foreach ($collection as $queueEntry) {
            $queueEntry->setStartedAt($this->magentoTimeZone->date());
            $queueEntry->setStatus(ProductUpdateQueueInterface::STATUS_VALUE_PROCESSING);
            try {
                $this->queueRepository->save($queueEntry);
            } catch (AlreadyExistsException $e) {
                $this->getLogger()->exception($e);
            }
        }
    }

    /**
     * Sets the timestamp for completed at & updates the status to be done
     *
     * @param QueueCollection $collection
     */
    private function notifyEndProcessing(QueueCollection $collection)
    {
        /* @var ProductUpdateQueueInterface $queueEntry */
        foreach ($collection as $queueEntry) {
            $queueEntry->setCompletedAt($this->magentoTimeZone->date());
            $queueEntry->setStatus(ProductUpdateQueueInterface::STATUS_VALUE_DONE);
            try {
                $this->queueRepository->save($queueEntry);
            } catch (AlreadyExistsException $e) {
                $this->getLogger()->exception($e);
            }
        }
        $this->cleanupUpdateQueue();
    }

    /**
     * Cleans up completed entries from the queue table
     */
    private function cleanupUpdateQueue()
    {
        try {
            $processed = $this->queueCollectionBuilder
                ->withCompletedHrsAgo(self::CLEANUP_INTERVAL_HRS)
                ->build();
            $this->getLogger()->debug(sprintf(
                'Cleaning up %d completed entries from update queue',
                $processed->count()
            ));
            foreach ($processed as $queueItem) {
                $this->queueRepository->delete($queueItem);
            }
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }
}
