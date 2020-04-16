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
use Magento\Store\Model\Store;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
use Nosto\Tagging\Model\Product\Update\Queue;
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
     * @param NostoAccountHelper $nostoAccountHelper
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
        NostoAccountHelper $nostoAccountHelper,
        BulkPublisherInterface $upsertBulkPublisher,
        BulkPublisherInterface $deleteBulkPublisher,
        QueueRepository $queueRepository,
        TimezoneInterface $magentoTimeZone,
        QueueCollectionBuilder $queueCollectionBuilder,
        $maxProductsInBatch,
        $cleanUpInterval
    ) {
        parent::__construct($nostoDataHelper, $nostoAccountHelper, $logger);
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
     * @param Store $store
     */
    public function processQueueCollection(QueueCollection $collection, Store $store)
    {
        $initialCollectionlSize = $collection->getSize();
        $this->logDebugWithStore(
            sprintf(
                'Started processing %d of queue entires',
                $initialCollectionlSize
            ),
            $store
        );
        if ($initialCollectionlSize === 0) {
            $this->logInfoWithStore('No uprocessed queue entries in the update queue', $store);
            return;
        }
        $this->capCollection($collection, $store);
        $this->setStatusToProcessing($collection, $store);
        $merged = $this->mergeQueues($collection, $store);
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
        $this->setStatusToDone($collection, $store);
        $this->cleanupUpdateQueue($store);
        $this->logDebugWithStore(
            sprintf(
                'Processed %d of queue entires',
                $collection->count()
            ),
            $store
        );
    }

    /**
     * Caps the collection to the max amount of products in one batch
     *
     * @param QueueCollection $collection
     * @param Store $store
     */
    private function capCollection(QueueCollection $collection, Store $store)
    {
        $originalSize = $collection->count();
        $productIdCount = 0;
        $leftIds = 0;
        /** @var Queue $entry */
        foreach ($collection as $key => $entry) {
            if ($productIdCount > $this->maxProductsInBatch) {
                $leftIds += $entry->getProductIdCount();
                $collection->removeItemByKey($key);
            }
            $productIdCount += $entry->getProductIdCount();
        }
        $sizeAfterCap = $collection->count();
        if ($sizeAfterCap < $originalSize) {
            $this->logDebugWithStore(
                sprintf(
                    'QueueCollection capped from %d to %d - %d non-unique product ids remain in the queue',
                    $originalSize,
                    $sizeAfterCap,
                    $leftIds
                ),
                $store
            );
        }
    }

    /**
     * Merges productIds from QueueCollection into an array containing only unique product ids per store
     *
     * @param QueueCollection $collection
     * @param Store $store
     * @return array
     */
    private function mergeQueues(QueueCollection $collection, Store $store)
    {
        $merged = [];
        $totalCount = 0;
        /* @var ProductUpdateQueueInterface $queueEntry */
        foreach ($collection as $queueEntry) {
            if (!isset($merged[$queueEntry->getStoreId()])) {
                $merged[$queueEntry->getStoreId()] = [
                    ProductUpdateQueueInterface::ACTION_VALUE_UPSERT => [],
                    ProductUpdateQueueInterface::ACTION_VALUE_DELETE => []
                ];
            }
            $totalCount += $queueEntry->getProductIdCount();
            foreach ($queueEntry->getProductIds() as $productId) {
                $merged[$queueEntry->getStoreId()][$queueEntry->getAction()][$productId] = $productId;
            }
        }
        $mergedCount = 0;
        foreach ($merged as $storeId => $arr) {
            foreach ($arr as $method => $ids) {
                $mergedCount += count($ids);
            }
        }
        $this->logDebugWithStore(
            sprintf(
                'Merged total of %d product ids into %d',
                $totalCount,
                $mergedCount
            ),
            $store
        );
        return $merged;
    }

    /**
     * Sets the timestamp for started at & updates the status to be processing
     *
     * @param QueueCollection $collection
     * @param Store $store
     */
    private function setStatusToProcessing(QueueCollection $collection, Store $store)
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
    private function setStatusToDone(QueueCollection $collection, Store $store)
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
    }

    /**
     * Cleans up completed entries from the queue table
     * @param Store $store
     */
    private function cleanupUpdateQueue(Store $store)
    {
        try {
            $processed = $this->queueCollectionBuilder
                ->init()
                ->withCompletedHrsAgo($this->cleanupInterval)
                ->build();
            $this->logDebugWithStore(
                sprintf(
                    'Cleaning up %d entries from update queue completed %d < hours ago',
                    $processed->count(),
                    $this->cleanupInterval
                ),
                $store
            );
            foreach ($processed as $queueItem) {
                $this->queueRepository->delete($queueItem);
            }
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }
}
