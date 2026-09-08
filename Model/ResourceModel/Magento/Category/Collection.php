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

namespace Nosto\Tagging\Model\ResourceModel\Magento\Category;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection as MagentoCategoryCollection;
use Magento\Framework\Exception\LocalizedException;

class Collection extends MagentoCategoryCollection
{
    /**
     * @return Collection
     * @throws LocalizedException
     */
    public function addActiveFilter(): Collection
    {
        return $this->addAttributeToFilter('status', ['eq' => 1]);
    }

    /**
     * @param array $ids
     * @return Collection
     * @throws LocalizedException
     */
    public function addIdsToFilter(array $ids): Collection
    {
        return $this->addAttributeToFilter($this->getIdFieldName(), ['in' => $ids]);
    }

    /**
     * Restricts the collection to categories that belong to the given root category's
     * tree, i.e. the root category itself and all of its descendants. Without this filter
     * categories from other websites/stores (which have their own, unrelated root category)
     * would be included in the collection as well.
     *
     * @param CategoryInterface $rootCategory
     * @return Collection
     * @throws LocalizedException
     */
    public function addRootCategoryFilter(CategoryInterface $rootCategory): Collection
    {
        // getPath() is declared nullable (@return string|null) and is treated as such
        // elsewhere in this module (see Model/Category/Builder.php); guard against it here
        // too, for consistency and to keep the filter well-defined (an unguarded null
        // would silently coalesce to an empty string in the concatenation below anyway,
        // but that's incidental rather than an explicit, self-documented contract).
        $path = $rootCategory->getPath() ?? '';
        return $this->addFieldToFilter(
            'path',
            [
                ['eq' => $path],
                ['like' => $path . '/%']
            ]
        );
    }
}
