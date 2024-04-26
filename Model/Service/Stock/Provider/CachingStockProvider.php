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

namespace Nosto\Tagging\Model\Service\Stock\Provider;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Website;

class CachingStockProvider implements StockProviderInterface
{
    /** @var StockProviderInterface */
    private StockProviderInterface $stockProvider;

    /** @var array */
    private array $quantityCache = [];

    /** @var array */
    private array $inStockCache = [];

    /** @var array */
    private array $productIdsInStockCache = [];

    /** @var int */
    private int $maxCacheSize;

    /**
     * CachingStockProvider constructor.
     * @param StockProviderInterface $stockProvider
     * @param int $maxCacheSize
     */
    public function __construct(
        StockProviderInterface $stockProvider,
        int $maxCacheSize
    ) {
        $this->stockProvider = $stockProvider;
        $this->maxCacheSize = $maxCacheSize;
    }

    /**
     * @inheritDoc
     */
    public function getAvailableQuantity(Product $product, Website $website)
    {
        if ($this->existsInQuantityCache($product->getId(), $website)) {
            return $this->getQuantityFromCache($product->getId(), $website);
        }
        $quantity = $this->stockProvider->getAvailableQuantity($product, $website);
        $this->saveQuantityToCache($product->getId(), $website, $quantity);
        return $quantity;
    }

    /**
     * @inheritDoc
     */
    public function isInStock(Product $product, Website $website)
    {
        if ($this->existsInStockCache($product, $website)) {
            return $this->getIsInStockFromCache($product, $website);
        }
        $inStock = $this->stockProvider->isInStock($product, $website);
        $this->saveToInStockCache($product, $website, $inStock);
        return $inStock;
    }

    /**
     * @inheritDoc
     */
    public function getQuantitiesByIds(array $productIds, Website $website)
    {
        $quantities = [];
        $nonCachedQuantities = [];
        foreach ($productIds as $productId) {
            if ($this->existsInQuantityCache($productId, $website)) {
                $quantities[] = $this->getQuantityFromCache($productId, $website);
            } else {
                $nonCachedQuantities[] = $productId;
            }
        }
        if (!empty($nonCachedQuantities)) {
            $lookedUpQuantities = $this->stockProvider->getQuantitiesByIds($nonCachedQuantities, $website);
            foreach ($lookedUpQuantities as $productId => $quantity) {
                $quantities[$productId] = $quantity;
                $this->saveQuantityToCache($productId, $website, $quantity);
            }
        }
        return $quantities;
    }

    /**
     * @param Product $product
     * @param Website $website
     * @param bool $inStock
     */
    private function saveToInStockCache(Product $product, Website $website, bool $inStock)
    {
        if (empty($this->inStockCache[$website->getId()])) {
            $this->inStockCache[$website->getId()] = [];
        }
        $this->inStockCache[$website->getId()][$product->getId()] = $inStock;
        $count = count($this->inStockCache[$website->getId()]);
        $offset = $count-$this->maxCacheSize;
        if ($offset > 0) {
            $this->inStockCache[$website->getId()] = array_slice(
                $this->inStockCache[$website->getId()],
                $offset,
                $this->maxCacheSize,
                true
            );
        }
    }

    /**
     * @param Product $product
     * @param Website $website
     * @return bool|null
     */
    private function getIsInStockFromCache(Product $product, Website $website)
    {
        if (!isset($this->inStockCache[$website->getId()][$product->getId()])) {
            return null;
        }
        return $this->inStockCache[$website->getId()][$product->getId()];
    }

    /**
     * @param string $productId
     * @param Website $website
     * @param int $quantity
     */
    private function saveQuantityToCache(string $productId, Website $website, int $quantity)
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
     * @param string $productId
     * @param Website $website
     * @return int|null
     */
    private function getQuantityFromCache(string $productId, Website $website)
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
     * @param string $productId
     * @param Website $website
     * @return bool
     */
    private function existsInQuantityCache(string $productId, Website $website)
    {
        return isset($this->quantityCache[$website->getId()][$productId]);
    }

    /**
     * @param array $productIds
     * @param Website $website
     * @return bool
     */
    private function existsInProductDataCache(array $productIds, Website $website)
    {
        $cacheKey = $this->generateCacheKeyProductIds($productIds);
        return isset($this->productIdsInStockCache[$website->getId()][$cacheKey]);
    }

    /**
     * @inheritDoc
     */
    public function getInStockProductIds(array $productIds, Website $website)
    {
        if ($this->existsInProductDataCache($productIds, $website)) {
            return $this->getInStockProductsFromCache($productIds, $website);
        }
        $lookedUpProductIds = $this->stockProvider->getInStockProductIds($productIds, $website);
        $this->saveToProductIdsInStockCache($productIds, $website, $lookedUpProductIds);
        return $lookedUpProductIds;
    }

    /**
     * @param array $productIds
     * @param Website $website
     * @return array|null
     */
    private function getInStockProductsFromCache(array $productIds, Website $website)
    {
        $cacheKey = $this->generateCacheKeyProductIds($productIds);
        if (!isset($this->productIdsInStockCache[$website->getId()][$cacheKey])) {
            return null;
        }
        return $this->productIdsInStockCache[$website->getId()][$cacheKey];
    }

    /**
     * @param array $givenIds
     * @param Website $website
     * @param $inStockIds
     */
    private function saveToProductIdsInStockCache(array $givenIds, Website $website, array $inStockIds)
    {
        if (empty($this->productIdsInStockCache[$website->getId()])) {
            $this->productIdsInStockCache[$website->getId()] = [];
        }
        $cacheKey = $this->generateCacheKeyProductIds($givenIds);
        $this->productIdsInStockCache[$website->getId()][$cacheKey] = $inStockIds;
        $count = count($this->productIdsInStockCache);
        $offset = $count-$this->maxCacheSize;
        if ($offset > 0) {
            $this->productIdsInStockCache = array_slice(
                $this->productIdsInStockCache,
                $offset,
                $this->maxCacheSize,
                true
            );
        }
    }

    /**
     * Generates a cache key for product ids
     *
     * @param array $productIds
     * @return string
     */
    private function generateCacheKeyProductIds(array $productIds)
    {
        return (string)implode('-', $productIds);
    }
}
