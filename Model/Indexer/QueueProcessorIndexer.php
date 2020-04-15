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

namespace Nosto\Tagging\Model\Indexer;

use Exception;
use Magento\Indexer\Model\ProcessManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\Invalidate\ModeSwitcher as InvalidateModeSwitcher;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollectionBuilder;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Nosto\Tagging\Model\Service\Update\QueueProcessorService;
use Nosto\Tagging\Model\Service\Update\QueueService;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class Invalidate
 * This class is responsible for listening to product changes
 * and setting the `is_dirty` value in `nosto_product_index` table
 * @package Nosto\Tagging\Model\Indexer
 */
class QueueProcessorIndexer extends AbstractIndexer
{
    const INDEXER_ID = 'nosto_index_product_queue_processor';

    private static $processingStores = [];

    /** @var QueueProcessorService */
    private $queueProcessorService;

    /** @var QueueCollectionBuilder */
    private $queueCollectionBuilder;

    /** @var InvalidateModeSwitcher */
    private $modeSwitcher;

    /**
     * Invalidate constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param QueueService $queueProcessorService
     * @param NostoLogger $logger
     * @param CollectionBuilder $queueCollectionBuilder
     * @param InvalidateModeSwitcher $modeSwitcher
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulation
     * @param ProcessManager $processManager
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     */
    public function __construct(
        NostoHelperScope $nostoHelperScope,
        QueueProcessorService $queueProcessorService,
        NostoLogger $logger,
        QueueCollectionBuilder $queueCollectionBuilder,
        InvalidateModeSwitcher $modeSwitcher,
        StoreDimensionProvider $dimensionProvider,
        Emulation $storeEmulation,
        ProcessManager $processManager,
        InputInterface $input,
        IndexerStatusServiceInterface $indexerStatusService
    ) {
        $this->queueProcessorService = $queueProcessorService;
        $this->queueCollectionBuilder = $queueCollectionBuilder;
        $this->modeSwitcher = $modeSwitcher;
        parent::__construct(
            $nostoHelperScope,
            $logger,
            $dimensionProvider,
            $storeEmulation,
            $input,
            $indexerStatusService,
            $processManager
        );
    }

    /**
     * @inheritdoc
     */
    public function getModeSwitcher(): ModeSwitcherInterface
    {
        return $this->modeSwitcher;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function doIndex(Store $store, array $ids = [])
    {
        if (in_array($store->getId(), self::$processingStores, true)) {
            return;
        }
        self::$processingStores[] = $store->getId();
        $collection = $this->getCollection($store);
        $this->queueProcessorService->processQueueCollection($collection);
    }

    /**
     * @inheritdoc
     */
    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return QueueCollection
     */
    public function getCollection(Store $store)
    {
        // Fetch always all queue entries having status new.
        // It makes the merging of queues more efficient.
        return $this->queueCollectionBuilder
            ->initDefault($store)
            ->withStatusNew()
            ->build();
    }
}
