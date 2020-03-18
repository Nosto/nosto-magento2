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

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;

/**
 * Class QueueService
 */
class QueueProcessorService extends AbstractService
{
    const PRODUCTID_BATCH_SIZE = 1000;
    const PRODUCT_DELETION_BATCH_SIZE = 1000;

    /** @var BulkPublisherInterface */
    private $bulkPublisher;

    /** @var QueueRepository */
    private $queueRepository;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /**
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param BulkPublisherInterface $bulkPublisher
     * @param QueueRepository $queueRepository
     * @param TimezoneInterface $magentoTimeZone
     */
    public function __construct(
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        BulkPublisherInterface $bulkPublisher,
        QueueRepository $queueRepository,
        TimezoneInterface $magentoTimeZone
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->bulkPublisher = $bulkPublisher;
        $this->queueRepository = $queueRepository;
        $this->magentoTimeZone = $magentoTimeZone;
    }

    /**
     * Processes a collection of queue entries
     * - merges product ids from queue entries within the same store
     * @param QueueCollection $collection
     */
    public function processQueueCollection(QueueCollection $collection)
    {
        $this->notifyStartProcessing($collection);
        $merged = $this->mergeQueues($collection);
        foreach ($merged as $storeId => $productIds) {
            $this->bulkPublisher->execute($storeId, $productIds);
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
        foreach ($collection->getItems() as $queueEntry) {
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
            $this->queueRepository->save($queueEntry);
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
            $this->queueRepository->save($queueEntry);
        }
        // TODO: remove old rows from the table - say 6hrs old
    }
}
