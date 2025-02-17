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
use Nosto\Tagging\Exception\ParentCategoryDisabledException;

/**
 * Repository wrapper class for fetching categories
 */
class Repository
{
    private array $parentCategoryIdCache = [];

    /**
     *  Gets the parent category ID's for a given category
     *
     * @param CategoryInterface $category
     * @return string[]|null
     *
     * @throws ParentCategoryDisabledException
     */
    public function resolveParentCategoryIds(CategoryInterface $category): ?array
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
            $parentCategoryIds = $this->getCategoryParentIds($category->getPath());
            $this->saveParentIdsToCache($category, $parentCategoryIds);
        }

        return $parentCategoryIds;
    }

    /**
     * Get Parent Category IDS
     *
     * @param string $path
     * @return array
     */
    private function getCategoryParentIds(string $path): array
    {
        $parentCategories = explode('/', $path);
        array_pop($parentCategories);
        return $parentCategories;
    }

    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param CategoryInterface $category
     * @return string[]|null
     */
    private function getParentIdsFromCache(CategoryInterface $category)
    {
        return $this->parentCategoryIdCache[$category->getId()] ?? null;
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
