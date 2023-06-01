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
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\ProcessManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Api\Data\ProductIndexerIgnoranceInterface;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\Product\ModeSwitcher as ProductModeSwitcher;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use Symfony\Component\Console\Input\InputInterface;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Model\ProductIndexerIgnorance\RepositoryFactory as IgnoranceRepositoryFactory;
use Nosto\Tagging\Model\ProductIndexerIgnorance\Repository as IgnoranceRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class ProductIndexer
 * Fetches product ID's from CL tables and create entries in the message queue
 */
class ProductIndexer extends AbstractIndexer
{
    public const INDEXER_ID = 'nosto_index_product';

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /** @var CollectionBuilder */
    private CollectionBuilder $productCollectionBuilder;

    /** @var ProductModeSwitcher */
    private ProductModeSwitcher $modeSwitcher;

    /** @var IndexerRegistry  */
    private IndexerRegistry $indexerRegistry;

    /**
     * @var IgnoranceRepositoryFactory
     */
    private IgnoranceRepositoryFactory $ignoranceRepositoryFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;


    /**
     * Invalidate constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param ProductUpdateService $productUpdateService
     * @param NostoLogger $logger
     * @param CollectionBuilder $productCollectionBuilder
     * @param ProductModeSwitcher $modeSwitcher
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulation
     * @param ProcessManager $processManager
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     * @param IndexerRegistry $indexerRegistry
     * @param IgnoranceRepositoryFactory $ignoranceRepositoryFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        NostoHelperScope              $nostoHelperScope,
        ProductUpdateService          $productUpdateService,
        NostoLogger                   $logger,
        CollectionBuilder             $productCollectionBuilder,
        ProductModeSwitcher           $modeSwitcher,
        StoreDimensionProvider        $dimensionProvider,
        Emulation                     $storeEmulation,
        ProcessManager                $processManager,
        InputInterface                $input,
        IndexerStatusServiceInterface $indexerStatusService,
        IndexerRegistry               $indexerRegistry,
        IgnoranceRepositoryFactory    $ignoranceRepositoryFactory,
        SearchCriteriaBuilder         $searchCriteriaBuilder
    ) {
        $this->productUpdateService = $productUpdateService;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->modeSwitcher = $modeSwitcher;
        $this->indexerRegistry = $indexerRegistry;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;

        parent::__construct(
            $nostoHelperScope,
            $logger,
            $dimensionProvider,
            $storeEmulation,
            $input,
            $indexerStatusService,
            $processManager
        );
        $this->ignoranceRepositoryFactory = $ignoranceRepositoryFactory;
    }

    /**
     * @inheritDoc
     */
    public function getModeSwitcher(): ModeSwitcherInterface
    {
        return $this->modeSwitcher;
    }

    /**
     * @return bool
     */
    private function isSchedule(): bool
    {
        $mageIndexer = $this->indexerRegistry->get(self::INDEXER_ID);
        return $mageIndexer->isScheduled();
    }

    /**
     * @inheritDoc
     * @throws NostoException
     * @throws Exception
     */
    public function doIndex(Store $store, array $ids = [])
    {
        $collection = $this->getCollection($store, $ids);
        $this->productUpdateService->addCollectionToUpdateMessageQueue(
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
     */
    private function handleDeletedProducts(ProductCollection $existingCollection, Store $store, array $givenIds)
    {
        if (!empty($givenIds)) {

            if ($this->isSchedule()) {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('action', ProductIndexerIgnoranceInterface::ACTION_DELETE, 'eq')
                    ->addFilter('entity_id', $givenIds, 'in')
                    ->create();

                /** @var IgnoranceRepository $indexerIgnoranceRepository */
                $indexerIgnoranceRepository = $this->ignoranceRepositoryFactory->create();

                $items = $indexerIgnoranceRepository->search($searchCriteria)->getItems();

                $ignoreIds = array_map(function ($item) {
                    return $item->getEntityId();
                }, $items);

                $givenIds = array_diff($givenIds, $ignoreIds);
                $indexerIgnoranceRepository->deleteByIds($ignoreIds);
            }

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
                $this->productUpdateService->addIdsToDeleteMessageQueue($removed, $store);
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
