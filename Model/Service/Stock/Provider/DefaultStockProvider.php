<?php /** @noinspection PhpUnused */

/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Service\Stock\Provider;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Store\Model\Website;

class DefaultStockProvider implements StockProviderInterface
{
    private $stockRegistryProvider;

    /**
     * DefaultStockProvider constructor.
     * @param StockRegistryProvider $stockRegistryProvider
     */
    public function __construct(StockRegistryProvider $stockRegistryProvider)
    {
        $this->stockRegistryProvider = $stockRegistryProvider;
    }

    /**
     * Returns stock item from the default source
     *
     * @param Product $product
     * @return StockItemInterface
     */
    private function getStockItem(Product $product)
    {
        return $this->stockRegistryProvider->getStockItem(
            $product->getId(),
            StockRegistryProvider::DEFAULT_STOCK_SCOPE
        );
    }

    /**
     * @inheritDoc
     * @noinspection PhpUnusedParameterInspection
     */
    public function getAvailableQuantity(Product $product, Website $website)
    {
        return (int)$this->getStockItem($product)->getQty();
    }

    /**
     * @inheritDoc
     * @noinspection PhpUnusedParameterInspection
     */
    public function isInStock(Product $product, Website $website)
    {
        return (bool)$this->getStockItem($product)->getIsInStock();
    }

    /**
     * @inheritDoc
     */
    public function getQuantitiesByIds(array $productIds, Website $website)
    {
        $quantities = [];
        $stockItems = $this->getStockStatuses($productIds, $website);
        /* @var Product $product */
        foreach ($stockItems as $stockItem) {
            $quantities[$stockItem->getProductId()] = $stockItem->getQty();
        }
        return $quantities;
    }

    /**
     * @param array $ids
     * @return StockStatusInterface[]
     * @noinspection PhpUnusedParameterInspection
     */
    private function getStockStatuses(array $ids, Website $website)
    {
        return $this->stockRegistryProvider->getStockStatuses(
            $ids,
            StockRegistryProvider::DEFAULT_STOCK_SCOPE
        )->getItems();
    }
}
