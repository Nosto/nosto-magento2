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

use Magento\CatalogInventory\Api\Data\StockStatusInterface;

class MemoryStockProvider implements StockProvider
{
    const MAX_CACHED_ITEMS = 1000;

    private $runTimeCache = [];

    /**
     * @var StockProvider
     */
    private $stockProvider;

    public function __construct(StockProvider $stockProvider)
    {
        $this->stockProvider = $stockProvider;
    }

    /**
     * @inheritDoc
     */
    public function getQuantities($ids)
    {
        $lookups = array();
        $idsInArray = array_filter($ids, "existsInCache");
        $notInArray = array_diff($ids, $idsInArray);
        foreach ($idsInArray as $id) {
            $lookups[] = $this->getQtyFromCache($id);
        }

        $fromQty[] = $this->stockProvider->getQuantities($notInArray);
        /** @var StockStatusInterface $item */
        foreach ($fromQty as $item) {
            $lookups[] = $item;
            $this->saveQtyToCache($item);
        }

        return $lookups;

    }

    /**
     * @param int $productId
     * @return StockStatusInterface
     */
    private function getQtyFromCache($productId)
    {
        if (!isset($this->runTimeCache[$productId])) {
            return null;
        }
        return $this->runTimeCache[$productId];
    }

    /**
     * @param StockStatusInterface $item
     */
    private function saveQtyToCache(StockStatusInterface $item)
    {
        $this->runTimeCache[$item->getProductId()] = $item;
        $this->runTimeCache = array_slice($this->runTimeCache, 0, self::MAX_CACHED_ITEMS);
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(int $id)
    {
        if ($this->existsInCache($id)) {
            return $this->getQtyFromCache($id);
        } else {
            $item = $this->stockProvider->getQuantity($id);
            $this->saveQtyToCache($item);
            return $item;
        }

    }

    private function existsInCache($productId): bool
    {
        return isset($this->runTimeCache[$productId]);
    }
}