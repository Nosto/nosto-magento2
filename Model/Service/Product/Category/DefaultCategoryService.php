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

namespace Nosto\Tagging\Model\Service\Product\Category;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class DefaultCategoryService implements CategoryServiceInterface
{

    private NostoLogger $logger;
    private CollectionFactory $categoryCollectionFactory;
    private ManagerInterface $eventManager;

    /**
     * Builder constructor.
     * @param CollectionFactory $categoryCollectionFactory
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        CollectionFactory $categoryCollectionFactory,
        NostoLogger $logger,
        ManagerInterface $eventManager
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * Get Nosto Product
     * If is not indexed or dirty, rebuilds, saves product to the indexed table
     * and returns NostoProduct from indexed product
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    public function getCategories(Product $product, StoreInterface $store)
    {
        $categories = [];
        /** @phan-suppress-next-line PhanUndeclaredMethod */
        foreach ($product->getCategoryCollection()->setStore($store->getId()) as $category) {
            $categoryString = $this->getCategory($category, $store);
            if (!empty($categoryString)) {
                $categories[] = $categoryString;
            }
        }

        return $categories;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(Category $category, StoreInterface $store)
    {
        $nostoCategory = '';
        try {
            $data = [];
            $categoryIds = [];
            $path = $category->getPath();
            foreach (explode('/', $path) as $categoryId) {
                $categoryIds[] = $categoryId;
            }

            $categories = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('level')
                ->addAttributeToFilter('entity_id', $categoryIds)
                ->setStore($store->getId())
                ->addAttributeToSort('level');
            foreach ($categories as $cat) {
                if ($cat instanceof Category
                    && $cat->getLevel() > 1
                    && !empty($cat->getName())
                ) {
                    $data[] = $cat->getName();
                }
            }
            $nostoCategory = count($data) ? '/' . implode('/', $data) : '';
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        if (empty($nostoCategory)) {
            $nostoCategory = null;
        } else {
            $this->eventManager->dispatch(
                'nosto_category_string_load_after',
                ['categoryString' => $nostoCategory, 'magentoCategory' => $category]
            );
        }

        return $nostoCategory ? trim($nostoCategory) : $nostoCategory;
    }

    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    public function getCategoryParentIds(Product $product, StoreInterface $store)
    {
        $parentCategoriesIds = [];
        /** @phan-suppress-next-line PhanUndeclaredMethod */
        foreach ($product->getCategoryCollection()->setStore($store->getId()) as $category) {
            foreach ($category->getParentIds() as $parentId) {
                $parentCategoriesIds[] = $parentId;
            }
        }
        return array_values(array_unique($parentCategoriesIds)); // Sequential keys to avoid serialization issues
    }
}
