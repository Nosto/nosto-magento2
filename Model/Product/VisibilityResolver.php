<?php
/**
 * Copyright (c) 2026, Nosto Solutions Ltd
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
 * @copyright 2026 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;

/**
 * Resolves a product's effective visibility at a specific store's scope.
 *
 * Visibility is a store-scoped attribute: a store with its own override keeps that
 * value regardless of what the default (store 0) value is. Callers that only just
 * changed the default value must re-check each affected store here rather than
 * assume the change applies everywhere.
 */
class VisibilityResolver
{
    /** @var CollectionBuilder */
    private CollectionBuilder $productCollectionBuilder;

    /**
     * @param CollectionBuilder $productCollectionBuilder
     */
    public function __construct(CollectionBuilder $productCollectionBuilder)
    {
        $this->productCollectionBuilder = $productCollectionBuilder;
    }

    /**
     * Returns, out of the given ids, the ones that resolve to a visibility other
     * than "Not Visible Individually" at the given store's scope (the store's own
     * override if it has one, otherwise the default value)
     *
     * @param int[] $productIds
     * @param Store $store
     * @return int[]
     */
    public function getIndividuallyVisibleProductIds(array $productIds, Store $store): array
    {
        if (empty($productIds)) {
            return [];
        }

        $collection = $this->productCollectionBuilder
            ->withStore($store)
            ->withIds($productIds)
            ->build();
        $collection->addAttributeToSelect(ProductInterface::VISIBILITY);

        $ids = [];
        foreach ($collection->getItems() as $item) {
            if ((int)$item->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE) {
                $ids[] = (int)$item->getId();
            }
        }
        return $ids;
    }
}
