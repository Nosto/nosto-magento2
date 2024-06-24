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

namespace Nosto\Tagging\Model\Category;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Nosto\Tagging\Exception\ParentCategoryDisabledException;

/**
 * Repository wrapper class for fetching categories
 */
class Repository
{
    private array $parentCategoryIdCache = [];

    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /** @var CategoryRepository $categoryRepository */
    private CategoryRepository $categoryRepository;

    /** @var CategoryCollectionFactory $categoryCollectionFactory */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CategoryRepository $categoryRepository,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Gets categories by category ids
     *
     * @param array $ids
     */
    public function getByIds(array $ids)
    {
        //@TODO implement
    }


    /**
     * @param Store $store
     * @param array $categoryIds
     *
     * @return CategoryCollection
     * @throws LocalizedException
     */
    public function getCategoryCollectionQuery(Store $store, array $categoryIds = [])
    {
        $categories = $this->categoryCollectionFactory->create()
            ->distinct(true)
            ->addNameToResult()
            ->setStoreId($store->getId())
            ->addUrlRewriteToResult()
            ->addAttributeToFilter('level', ['gt' => 1]) // @TODO: Check if zero level categories are needed
            ->addAttributeToSelect(array_merge(['name', 'is_active', 'include_in_menu']))
            ->addOrderField('entity_id');

        if ($categoryIds) {
            $categories->addAttributeToFilter('entity_id', ['in' => $categoryIds]);
        }

        return $categories;
    }


    /**
     * Gets the parent category ID's for a given category
     *
     * @param CategoryInterface $category
     * @return string[]|null
     * @throws ParentCategoryDisabledException
     */
    public function resolveParentCategoryIds(CategoryInterface $category)
    {
        if ($this->getParentIdsFromCache($category)) {
            return $this->getParentIdsFromCache($category);
        }

        if ($category->getLevel() < 1 && !$category->getIsActive()) {
            throw new ParentCategoryDisabledException(
                sprintf(
                    'Category with id %s is disabled',
                    $category->getId()
                )
            );
        }

        $parentCategoryIds = null;
        if ($category->getLevel() >= 1) {
            //@TODO: get parents from root category, check if this works
            $parentCategoryIds = $category->getParentIds();
            $this->saveParentIdsToCache($category, $parentCategoryIds);
        }
        return $parentCategoryIds;
    }


    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param CategoryInterface $category
     * @return string[]|null
     */
    private function getParentIdsFromCache(CategoryInterface $category)
    {
        if (isset($this->parentCategoryIdCache[$category->getId()])) {
            return $this->parentCategoryIdCache[$category->getId()];
        }

        return null;
    }

    /**
     * Saves the parents category ids to internal cache to avoid redundant
     * database queries
     *
     * @param CategoryInterface $category
     * @param string[] $parentCategoryIds
     */
    private function saveParentIdsToCache(CategoryInterface $category, array $parentCategoryIds)
    {
        $this->parentCategoryIdCache[$category->getId()] = $parentCategoryIds;
    }

}
