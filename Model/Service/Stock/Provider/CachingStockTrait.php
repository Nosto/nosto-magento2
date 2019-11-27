<?php /** @noinspection PhpUnused */

namespace Nosto\Tagging\Model\Service\Stock\Provider;

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

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\Store\Model\Website;

trait CachingStockTrait
{
    private $quantityCache = [];
    private $inStockCache = [];
    private $maxCacheSize;

    /**
     * @param int $maxCacheSize
     */
    public function __construct($maxCacheSize)
    {
        $this->maxCacheSize = $maxCacheSize;
    }

    /**
     * @param Product $product
     * @param Website $website
     * @param $inStock
     */
    private function saveToInStockCache(Product $product, Website $website, $inStock)
    {
        if (empty($this->inStockCache[$website->getId()])) {
            $this->inStockCache[$website->getId()] = [];
        }
        $this->inStockCache[$website->getId()][$product->getId()] = $inStock;
        $count = count($this->inStockCache[$website->getId()]);
        $offset = $count-$this->maxCacheSize;
        if ($offset > 0) {
            $this->inStockCache = array_slice($this->inStockCache[$website->getId()], $offset, $this->maxCacheSize, true);
        }
    }

    /**
     * @param Product $product
     * @param Website $website
     * @return StockStatusInterface|null
     */
    private function getIsInStockFromCache(Product $product, Website $website)
    {
        if (!isset($this->inStockCache[$website->getId()][$product->getId()])) {
            return null;
        }
        return $this->inStockCache[$website->getId()][$product->getId()];
    }

    /**
     * @param $productId
     * @param Website $website
     * @param $quantity
     */
    private function saveQuantityToCache($productId, Website $website, $quantity)
    {
        if (empty($this->quantityCache[$website->getId()])) {
            $this->quantityCache[$website->getId()] = [];
        }
        $this->quantityCache[$website->getId()][$productId] = $quantity;
        $count = count($this->quantityCache);
        $offset = $count-$this->maxCacheSize;
        if ($offset > 0) {
            $this->quantityCache = array_slice($this->quantityCache, $offset, $this->maxCacheSize, true);
        }
    }

    /**
     * @param int $productId
     * @return StockStatusInterface|null
     */
    private function getQuantityFromCache($productId, Website $website)
    {
        if (!isset($this->quantityCache[$website->getId()][$productId])) {
            return null;
        }
        return $this->quantityCache[$website->getId()][$productId];
    }

    /**
     * @param Product $product
     * @param Website $website
     * @return bool
     */
    private function existsInStockCache(Product $product, Website $website)
    {
        return isset($this->inStockCache[$website->getId()][$product->getId()]);
    }

    /**
     * @param $productId
     * @return bool
     */
    private function existsInQuantityCache($productId, Website $website)
    {
        return isset($this->quantityCache[$website->getId()][$productId]);
    }

}
