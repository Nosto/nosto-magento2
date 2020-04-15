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
use Nosto\Tagging\Model\Product\Update\Queue;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollectionBuilder;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;

/**
 * Class QueueService
 */
class QueueProcessorService extends AbstractService
{
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

    /** @var int */
    private $maxProductsInBatch;

    /** @var int */
    private $cleanupInterval;

    /**
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param BulkPublisherInterface $upsertBulkPublisher
     * @param BulkPublisherInterface $deleteBulkPublisher
     * @param QueueRepository $queueRepository
     * @param TimezoneInterface $magentoTimeZone
     * @param QueueCollectionBuilder $queueCollectionBuilder
     * @param $maxProductsInBatch
     * @param $cleanUpInterval
     */
    public function __construct(
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        BulkPublisherInterface $upsertBulkPublisher,
        BulkPublisherInterface $deleteBulkPublisher,
        QueueRepository $queueRepository,
        TimezoneInterface $magentoTimeZone,
        QueueCollectionBuilder $queueCollectionBuilder,
        $maxProductsInBatch,
        $cleanUpInterval
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->upsertBulkPublisher = $upsertBulkPublisher;
        $this->deleteBulkPublisher = $deleteBulkPublisher;
        $this->queueRepository = $queueRepository;
        $this->magentoTimeZone = $magentoTimeZone;
        $this->queueCollectionBuilder = $queueCollectionBuilder;
        $this->maxProductsInBatch = $maxProductsInBatch;
        $this->cleanupInterval = $cleanUpInterval;
    }

    /**
     * Processes a collection of queue entries
     * - merges product ids from queue entries within the same store
     * @param QueueCollection $collection
     */
    public function processQueueCollection(QueueCollection $collection)
    {
        if ($collection->getSize() === 0) {
            $this->getLogger()->debug('No uprocessed queue entries in the update queue');
            return;
        }
        $this->capCollection($collection);
        $this->notifyStartProcessing($collection);
        $merged = $this->mergeQueues($collection);
        foreach ($merged as $storeId => $actions) {
            foreach ($actions as $action => $productIds) {
                switch ($action) {
                    case ProductUpdateQueueInterface::ACTION_VALUE_UPSERT:
                        $this->upsertBulkPublisher->execute($storeId, $productIds);
                        break;
                    case ProductUpdateQueueInterface::ACTION_VALUE_DELETE:
                        $this->deleteBulkPublisher->execute($storeId, $productIds);
                }
            }
        }
        $this->notifyEndProcessing($collection);
    }

    /**
     * Caps the collection to the max amount of products in one batch
     *
     * @param QueueCollection $collection
     */
    private function capCollection(QueueCollection $collection)
    {
        $productIdCount = 0;
        /** @var Queue $entry */
        foreach ($collection as $key => $entry) {
            if ($productIdCount > $this->maxProductsInBatch) {
                $collection->removeItemByKey($key);
            }
            $productIdCount += $entry->getProductIdCount();
        }
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
                $merged[$queueEntry->getStoreId()] = [
                    ProductUpdateQueueInterface::ACTION_VALUE_UPSERT => [],
                    ProductUpdateQueueInterface::ACTION_VALUE_DELETE => []
                ];
            }
            foreach ($queueEntry->getProductIds() as $productId) {
                $merged[$queueEntry->getStoreId()][$queueEntry->getAction()][$productId] = $productId;
            }
        }
        return $merged;
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
                ->init()
                ->withCompletedHrsAgo($this->cleanupInterval)
                ->build();
            $this->getLogger()->debug(
                sprintf(
                    'Cleaning up %d entries from update queue completed %d < hours ago',
                    $processed->count(),
                    $this->cleanupInterval
                )
            );
            foreach ($processed as $queueItem) {
                $this->queueRepository->delete($queueItem);
            }
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }
}
