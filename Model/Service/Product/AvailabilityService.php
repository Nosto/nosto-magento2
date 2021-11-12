<?php
/**
 * Copyright (c) 2021, Nosto Solutions Ltd
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
 * @copyright 2021 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Service\Product;

use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Model\Service\Stock\StockService;

class AvailabilityService
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var StockService */
    private $stockService;

    /**
     * AvailabiltyService constructor.
     * @param StoreManagerInterface $storeManager
     * @param StockService $stockService
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        StockService $stockService
    ) {
        $this->storeManager = $storeManager;
        $this->stockService = $stockService;
    }

    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return bool
     */
    public function isAvailableInStore(Product $product, StoreInterface $store)
    {
        if ($this->storeManager->isSingleStoreMode()) {
            return $product->isAvailable();
        }
        return in_array($store->getId(), $product->getStoreIds(), false);
    }

    /**
     * Checks if the product is in stock
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return bool
     */
    public function isInStock(Product $product, StoreInterface $store)
    {
        return $this->stockService->isInStock($product, $store);
    }
}
