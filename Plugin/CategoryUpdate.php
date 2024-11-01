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

namespace Nosto\Tagging\Plugin;

use Closure;
use Magento\Catalog\Model\ResourceModel\Category as MagentoResourceCategory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Indexer\CategoryIndexer;
use Nosto\Tagging\Model\Category\Repository as NostoCategoryRepository;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionBuilder;
use Nosto\Tagging\Model\Service\Update\CategoryUpdateService;

/**
 * Plugin for product updates
 */
class CategoryUpdate
{
    /** @var IndexerRegistry  */
    private IndexerRegistry $indexerRegistry;

    /** @var CategoryIndexer  */
    private CategoryIndexer $categoryIndexer;

    /** @var NostoCategoryRepository  */
    private NostoCategoryRepository $nostoCategoryRepository;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /** @var CategoryUpdateService */
    private CategoryUpdateService $categoryUpdateService;

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /** @var CollectionBuilder */
    private CollectionBuilder $categoryCollectionBuilder;

    /**
     * ProductUpdate constructor.
     * @param IndexerRegistry $indexerRegistry
     * @param CategoryIndexer $categoryIndexer
     * @param NostoCategoryRepository $nostoCategoryRepository
     * @param NostoLogger $logger
     * @param CategoryUpdateService $categoryUpdateService
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(
        IndexerRegistry                $indexerRegistry,
        CategoryIndexer                $categoryIndexer,
        NostoCategoryRepository        $nostoCategoryRepository,
        NostoLogger                    $logger,
        CategoryUpdateService          $categoryUpdateService,
        NostoHelperScope               $nostoHelperScope,
        CollectionBuilder              $categoryCollectionBuilder
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->categoryIndexer = $categoryIndexer;
        $this->nostoCategoryRepository = $nostoCategoryRepository;
        $this->logger = $logger;
        $this->categoryUpdateService = $categoryUpdateService;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->categoryCollectionBuilder = $categoryCollectionBuilder;
    }

    public function aroundSave(
        MagentoResourceCategory $resourceCategory,
        Closure $proceed,
        AbstractModel $category
    ) {
        $storeIds = $category->getStoreIds();
        $categoryCollection = $this->categoryCollectionBuilder->withIds([$category->getId()])->build();

         foreach ($storeIds as $storeId) {
            $store = $this->nostoHelperScope->getStore($storeId);
            $this->categoryUpdateService->addCollectionToUpdateMessageQueue($categoryCollection, $store);
        }


        return $proceed($category);

    }

}
