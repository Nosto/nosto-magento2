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

namespace Nosto\Tagging\Model\Service\Stock;

use Magento\Bundle\Model\Product\Type as Bundled;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Service\Stock\Provider\StockProviderInterface;

/**
 * StockService helper used for product inventory level related tasks.
 */
class StockService
{
    /** @var StockProviderInterface */
    private StockProviderInterface $stockProvider;

    /** @var Logger */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param StockProviderInterface $stockProvider
     * @param Logger $nostoLogger
     */
    public function __construct(
        StockProviderInterface $stockProvider,
        Logger $nostoLogger
    ) {
        $this->stockProvider = $stockProvider;
        $this->logger = $nostoLogger;
    }

    /**
     * Calculates the total qty in stock. If the product is configurable the
     * the sum of associated products will be calculated.
     *
     * @param Product $product
     * @param Store $store
     * @return int
     * @suppress PhanUndeclaredMethod
     * @suppress PhanDeprecatedFunction
     */
    public function getQuantity(Product $product, Store $store)
    {
        $qty = 0;
        try {
            $website = $store->getWebsite();
        } catch (NoSuchEntityException $e) {
            $this->logger->exception($e);
            return 0;
        }
        if ($website instanceof Website === false) {
            $this->logger->error('Could not resolve website when resolving quantity');
            return 0;
        }
        switch ($product->getTypeId()) {
            case ProductType::TYPE_BUNDLE:
                /** @var Bundled $productType */
                $productType = $product->getTypeInstance();
                $bundledItemIds = $productType->getChildrenIds($product->getId(), $required = true);
                $productIds = [];
                foreach ($bundledItemIds as $variants) {
                    if (is_array($variants) && count($variants) > 0) { // @codingStandardsIgnoreLine
                        foreach ($variants as $productId) {
                            $productIds[] = $productId;
                        }
                    }
                }
                $qty = $this->getMinQty($productIds, $website);
                break;
            case Grouped::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Grouped) {
                    $productIds = $productType->getAssociatedProductIds($product);
                    $qty = $this->getMinQty($productIds, $website);
                }
                break;
            case Configurable::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof Configurable) {
                    $productIds = $productType->getChildrenIds($product->getId());
                    if (isset($productIds[0]) && is_array($productIds[0])) {
                        $productIds = $productIds[0];
                    }
                    $qty = $this->getQtySum($productIds, $website);
                }
                break;
            default:
                $qty += $this->stockProvider->getAvailableQuantity($product, $website);
                break;
        }

        return $qty;
    }

    /**
     * Searches the minimum quantity from the products collection
     *
     * @param int[] $productIds
     * @param Website $website
     * @return int|mixed
     */
    private function getMinQty(array $productIds, Website $website)
    {
        $quantities = $this->stockProvider->getQuantitiesByIds($productIds, $website);
        $minQty = 0;
        if (!empty($quantities)) {
            rsort($quantities, SORT_NUMERIC);
            $minQty = array_pop($quantities);
        }
        return $minQty;
    }

    /**
     * Sums quantities for all product ids in array
     *
     * @param int[] $productIds
     * @param Website $website
     * @return int
     */
    private function getQtySum(array $productIds, Website $website)
    {
        $qty = 0;
        $quantities = $this->stockProvider->getQuantitiesByIds($productIds, $website);
        foreach ($quantities as $quantity) {
            $qty += $quantity;
        }
        return $qty;
    }

    /**
     * Sums quantities for all product ids in array
     *
     * @param Product $product
     * @param Store $store
     * @return bool
     */
    public function isInStock(Product $product, Store $store)
    {
        try {
            return (bool)$this->stockProvider->isInStock(
                $product,
                $store->getWebsite()
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->exception($e);
            return false;
        }
    }
}
