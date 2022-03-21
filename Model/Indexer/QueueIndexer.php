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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Indexer\Model\ProcessManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\Queue\ModeSwitcher as QueueModeSwitcher;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Nosto\Tagging\Model\Service\Update\QueueService;
use Nosto\Tagging\Util\PagingIterator;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class Invalidate
 * This class is responsible for listening to product changes
 * and setting the `is_dirty` value in `nosto_product_index` table
 */
class QueueIndexer extends AbstractIndexer
{
    public const INDEXER_ID = 'nosto_index_product_queue';

    /** @var QueueService */
    private $queueService;

    /** @var CollectionBuilder */
    private $productCollectionBuilder;

    /** @var QueueModeSwitcher */
    private $modeSwitcher;

    /**
     * Invalidate constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param QueueService $queueService
     * @param NostoLogger $logger
     * @param CollectionBuilder $productCollectionBuilder
     * @param QueueModeSwitcher $modeSwitcher
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulation
     * @param ProcessManager $processManager
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     */
    public function __construct(
        NostoHelperScope $nostoHelperScope,
        QueueService $queueService,
        NostoLogger $logger,
        CollectionBuilder $productCollectionBuilder,
        QueueModeSwitcher $modeSwitcher,
        StoreDimensionProvider $dimensionProvider,
        Emulation $storeEmulation,
        ProcessManager $processManager,
        InputInterface $input,
        IndexerStatusServiceInterface $indexerStatusService
    ) {
        $this->queueService = $queueService;
        $this->productCollectionBuilder = $productCollectionBuilder;
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
     * @inheritDoc
     */
    public function getModeSwitcher(): ModeSwitcherInterface
    {
        return $this->modeSwitcher;
    }

    /**
     * @inheritDoc
     * @throws NostoException
     * @throws Exception
     */
    public function doIndex(Store $store, array $ids = [])
    {
        $collection = $this->getCollection($store, $ids);
        $this->queueService->addCollectionToUpsertQueue(
            $collection,
            $store
        );
        $this->handleDeletedProducts($collection, $store, $ids);
    }

    /**
     * @param ProductCollection $existingCollection
     * @param Store $store
     * @param array $givenIds
     * @throws NostoException
     * @throws AlreadyExistsException
     */
    private function handleDeletedProducts(ProductCollection $existingCollection, Store $store, array $givenIds)
    {
        if (!empty($givenIds)) {
            $existingCollection->setPageSize(1000);
            $iterator = new PagingIterator($existingCollection);
            $present = [];
            foreach ($iterator as $page) {
                foreach ($page->getItems() as $item) {
                    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                    $id = $item->getId();
                    $present[$id] = $id;
                }
            }
            $removed = [];
            foreach ($givenIds as $productId) {
                if (!isset($present[$productId])) {
                    $removed[] = $productId;
                }
            }
            if (count($removed) > 0) {
                $this->queueService->addIdsToDeleteQueue($removed, $store);
            }
        }
    }
    /**
     * @inheritDoc
     */
    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return ProductCollection
     */
    public function getCollection(Store $store, array $ids = []): ProductCollection
    {
        $this->productCollectionBuilder->initDefault($store);
        if (!empty($ids)) {
            $this->productCollectionBuilder->withIds($ids);
        } else {
            $this->productCollectionBuilder->withDefaultVisibility($store);
        }
        return $this->productCollectionBuilder->build();
    }
}
