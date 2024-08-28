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
use Nosto\NostoException;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcher\ModeSwitcher as CategoryModeSwitcher;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcher\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionBuilder;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Nosto\Tagging\Model\Service\Update\CategoryUpdateService;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class CategoryIndexer
 * Fetches category ID's from CL tables and create entries in the message queue
 */
class CategoryIndexer extends AbstractIndexer
{
    public const INDEXER_ID = 'nosto_index_category';

    /** @var CategoryUpdateService */
    private CategoryUpdateService $categoryUpdateService;

    /** @var CategoryModeSwitcher */
    private CategoryModeSwitcher $modeSwitcher;

    /** @var CollectionBuilder */
    private CollectionBuilder $categoryCollectionBuilder;

    /**
     * Constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param CategoryUpdateService $categoryUpdateService
     * @param NostoLogger $logger
     * @param CollectionBuilder $categoryCollectionBuilder
     * @param CategoryModeSwitcher $modeSwitcher
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulation
     * @param ProcessManager $processManager
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     */
    public function __construct(
        NostoHelperScope              $nostoHelperScope,
        CategoryUpdateService         $categoryUpdateService,
        NostoLogger                   $logger,
        CollectionBuilder             $categoryCollectionBuilder,
        CategoryModeSwitcher          $modeSwitcher,
        StoreDimensionProvider        $dimensionProvider,
        Emulation                     $storeEmulation,
        ProcessManager                $processManager,
        InputInterface                $input,
        IndexerStatusServiceInterface $indexerStatusService
    ) {
        $this->categoryUpdateService = $categoryUpdateService;
        $this->modeSwitcher = $modeSwitcher;
        $this->categoryCollectionBuilder = $categoryCollectionBuilder;

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
        $this->categoryUpdateService->addCollectionToUpdateMessageQueue(
            $collection,
            $store
        );
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
     * @return CategoryCollection
     */
    public function getCollection(Store $store, array $ids = []) //: CategoryCollection
    {
        $this->categoryCollectionBuilder->initDefault($store);

        if (!empty($ids)) {
            $this->categoryCollectionBuilder->withIds($ids);
        } else {
            $this->categoryCollectionBuilder->withStore($store);
        }

        return null;
        // @TODO: Implement logic
        // return $this->categoryCollectionBuilder->build();
    }
}
