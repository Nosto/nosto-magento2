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

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Exception\ParentCategoryDisabledException;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Category\Repository as NostoCategoryRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionFactory as CategoryCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder as ProductCollectionBuilder;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;

class CategoryUpdateService extends AbstractUpdateService
{
    /** @var NostoCategoryRepository $nostoCategoryRepository */
    private NostoCategoryRepository $nostoCategoryRepository;

    /** @var ProductCollectionBuilder */
    private ProductCollectionBuilder $productCollectionBuilder;

    /** @var CategoryCollectionFactory */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /**
     * CategoryUpdateService constructor.
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoCategoryRepository $nostoCategoryRepository
     * @param BulkPublisherInterface $upsertBulkPublisher
     * @param ProductCollectionBuilder $productCollectionBuilder
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductUpdateService $productUpdateService
     * @param int $batchSize
     */
    public function __construct(
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        NostoAccountHelper $nostoAccountHelper,
        NostoCategoryRepository $nostoCategoryRepository,
        BulkPublisherInterface $upsertBulkPublisher,
        ProductCollectionBuilder $productCollectionBuilder,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductUpdateService $productUpdateService,
        int $batchSize
    ) {
        parent::__construct(
            $logger,
            $nostoDataHelper,
            $nostoAccountHelper,
            $upsertBulkPublisher,
            $batchSize
        );
        $this->nostoCategoryRepository = $nostoCategoryRepository;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productUpdateService = $productUpdateService;
    }

    /**
     * Sets the categories into the message queue
     *
     * @param CategoryCollection $collection
     * @param Store $store
     * @param bool $queueAffectedProductUpdates
     * @throws NostoException
     * @throws Exception
     */
    public function addCollectionToUpdateMessageQueue(
        CategoryCollection $collection,
        Store $store,
        bool $queueAffectedProductUpdates = true
    )
    {
        $this->queueCollectionUpdates($collection, $store, $queueAffectedProductUpdates);
    }

    /**
     * @return string
     */
    protected function getEntityLogLabel(): string
    {
        return 'categories';
    }

    /**
     * @param CategoryCollection $collection
     * @return array
     */
    protected function getEntityIdsForPage($collection): array
    {
        return $this->toParentCategoryIds($collection);
    }

    /**
     * @param CategoryCollection $collection
     * @param Store $store
     * @throws Exception
     */
    protected function afterPageQueued($collection, Store $store)
    {
        $this->addAffectedProductsToUpdateMessageQueue($collection, $store);
    }

    /**
     * Queue products assigned to changed categories so product payloads get refreshed category data.
     *
     * @param CategoryCollection $collection
     * @param Store $store
     * @throws Exception
     */
    private function addAffectedProductsToUpdateMessageQueue(CategoryCollection $collection, Store $store)
    {
        $categoryIds = $this->resolveAffectedCategoryIds($collection, $store);
        if (empty($categoryIds)) {
            return;
        }

        $productCollection = $this->productCollectionBuilder
            ->initDefault($store)
            ->withDefaultVisibility($store)
            ->build();
        $productCollection->addCategoriesFilter(['in' => $categoryIds]);

        $this->productUpdateService->addCollectionToUpdateMessageQueue($productCollection, $store);
    }

    /**
     * Expand each changed category to include its descendant categories as well.
     *
     * @param CategoryCollection $collection
     * @param Store $store
     * @return int[]
     * @throws Exception
     */
    private function resolveAffectedCategoryIds(CategoryCollection $collection, Store $store): array
    {
        $categoryIds = [];
        foreach ($this->resolveStoreAwareCategories($collection, $store) as $categoryModel) {
            $categoryIds[] = (int) $categoryModel->getId();
            $descendantIds = $categoryModel->getAllChildren(true);
            if (!empty($descendantIds)) {
                foreach ($descendantIds as $descendantId) {
                    $categoryIds[] = (int) $descendantId;
                }
            }
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * Resolve the page categories in the current store context with at most one reload query.
     *
     * @param CategoryCollection $collection
     * @param Store $store
     * @return Category[]
     */
    private function resolveStoreAwareCategories(CategoryCollection $collection, Store $store): array
    {
        $categoriesById = [];
        $categoryIdsToLoad = [];

        /** @var Category $category */
        foreach ($collection->getItems() as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            $categoryId = (int) $category->getId();
            if ((int) $category->getStoreId() === (int) $store->getId() && $category->getPath() !== '') {
                $categoriesById[$categoryId] = $category;
                continue;
            }

            $categoryIdsToLoad[] = $categoryId;
        }

        if (empty($categoryIdsToLoad)) {
            return $categoriesById;
        }

        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->setStore($store);
        $categoryCollection->addAttributeToSelect('path');
        $categoryCollection->addIdFilter(array_values(array_unique($categoryIdsToLoad)));

        foreach ($categoryCollection->getItems() as $loadedCategory) {
            if ($loadedCategory instanceof Category) {
                $categoriesById[(int) $loadedCategory->getId()] = $loadedCategory;
            }
        }

        return $categoriesById;
    }

    /**
     * @param CategoryCollection $collection
     * @return array
     */
    private function toParentCategoryIds(CategoryCollection $collection): array
    {
        $categoryIds = [];
        /** @var CategoryInterface $category */
        foreach ($collection->getItems() as $category) {
            try {
                /** @phan-suppress-next-line PhanTypeMismatchArgument */
                $parents = $this->nostoCategoryRepository->resolveParentCategoryIds($category);
            } catch (ParentCategoryDisabledException $e) {
                $this->getLogger()->debug($e->getMessage());
                continue;
            }
            if (!empty($parents)) {
                foreach ($parents as $id) {
                    $categoryIds[] = $id;
                }
                $categoryIds[] = $category->getId();
            } else {
                $categoryIds[] = $category->getId();
            }
        }

        return array_unique($categoryIds);
    }
}
