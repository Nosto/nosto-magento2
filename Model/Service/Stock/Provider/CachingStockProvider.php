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

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;

class CachingStockProvider implements StockProviderInterface
{
    const MAX_CACHED_ITEMS = 2;
    private $quantityCache = [];
    private $inStockCache = [];
    private $stockProvider;

    /**
     * CachingStockProvider constructor.
     * @param StockProviderInterface $stockProvider
     */
    public function __construct(StockProviderInterface $stockProvider)
    {
        $this->stockProvider = $stockProvider;
    }

    /**
     * @inheritDoc
     */
    public function getStockStatuses(array $ids)
    {
        $lookups = [];
        $cachedIds = array_filter($ids, [$this, 'existsInQuantityCache']);
        $notInArray = array_diff($ids, $cachedIds);
        foreach ($cachedIds as $id) {
            $lookups[] = $this->getQtyFromCache($id);
        }
        $fromQty = $this->stockProvider->getStockStatuses($notInArray);
        /** @var StockStatusInterface $item */
        foreach ($fromQty as $item) {
            $lookups[] = $item;
            $this->saveQtyToCache($item);
        }
        return $lookups;
    }

    /**
     * @inheritDoc
     */
    public function getStockStatus($id)
    {
        if ($this->existsInQuantityCache($id)) {
            return $this->getQtyFromCache($id);
        }
        $item = $this->stockProvider->getStockStatus($id);
        $this->saveQtyToCache($item);
        return $item;
    }

    /**
     * @inheritDoc
     */
    public function getStockItem($id, $websiteId)
    {
        if ($this->existsInStockCache($id, $websiteId)) {
            return $this->getIsInStockFromCache($id, $websiteId);
        }
        $item = $this->stockProvider->getStockItem($id, $websiteId);
        $this->saveToInStockCache($item, $websiteId);
        return $item;
    }

    /**
     * @param int $productId
     * @return StockStatusInterface|null
     */
    private function getQtyFromCache($productId)
    {
        if (!isset($this->quantityCache[$productId])) {
            return null;
        }
        return $this->quantityCache[$productId];
    }

    /**
     * @param StockItemInterface $item
     * @param $websiteId
     */
    private function saveToInStockCache(StockItemInterface $item, $websiteId)
    {
        if (empty($this->inStockCache[$websiteId])) {
            $this->inStockCache[$websiteId] = [];
        }
        $this->inStockCache[$websiteId][$item->getProductId()] = $item;
        $count = count($this->inStockCache);
        $offset = $count-self::MAX_CACHED_ITEMS;
        if ($offset > 0) {
            $this->inStockCache = array_slice($this->inStockCache, $offset, self::MAX_CACHED_ITEMS, true);
        }

        $this->inStockCache = array_slice($this->inStockCache, 0, self::MAX_CACHED_ITEMS);
    }

    /**
     * @param int $productId
     * @return StockStatusInterface|null
     */
    private function getIsInStockFromCache($productId, $websiteId)
    {
        if (!isset($this->inStockCache[$websiteId][$productId])) {
            return null;
        }
        return $this->inStockCache[$websiteId][$productId];
    }

    /**
     * @param StockStatusInterface $item
     */
    private function saveQtyToCache(StockStatusInterface $item)
    {
        $this->quantityCache[$item->getProductId()] = $item;
        $count = count($this->quantityCache);
        $offset = $count-self::MAX_CACHED_ITEMS;
        if ($offset > 0) {
            $this->quantityCache = array_slice($this->quantityCache, $offset, self::MAX_CACHED_ITEMS, true);
        }
    }

    /**
     * @param $productId
     * @return bool
     */
    private function existsInStockCache($productId, $websiteId)
    {
        return isset($this->inStockCache[$websiteId][$productId]);
    }

    /**
     * @param $productId
     * @return bool
     */
    private function existsInQuantityCache($productId)
    {
        return isset($this->quantityCache[$productId]);
    }
}
