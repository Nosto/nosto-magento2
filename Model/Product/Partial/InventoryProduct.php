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

namespace Nosto\Tagging\Model\Product\Partial;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Tagging\Model\Service\Stock\StockService;
use Nosto\Tagging\Model\Product\Builder as FullProductBuilder;

class InventoryProduct
{
    /** @var NostoDataHelper */
    private NostoDataHelper $nostoDataHelper;

    /** @var NostoUrlBuilder */
    private NostoUrlBuilder $urlBuilder;

    /** @var StockService */
    private StockService $stockService;

    /** @var FullProductBuilder */
    private FullProductBuilder $fullProductBuilder;

    /** @var NostoSkuCollection */
    private NostoSkuCollection $skuCollection;

    /**
     * Builder constructor.
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoUrlBuilder $urlBuilder
     * @param StockService $stockService
     * @param FullProductBuilder $fullProductBuilder
     * @param NostoSkuCollection $skuCollection
     */
    public function __construct(
        NostoDataHelper $nostoDataHelper,
        NostoUrlBuilder $urlBuilder,
        StockService $stockService,
        FullProductBuilder $fullProductBuilder,
        NostoSkuCollection $skuCollection,
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->urlBuilder = $urlBuilder;
        $this->stockService = $stockService;
        $this->fullProductBuilder = $fullProductBuilder;
        $this->skuCollection = $skuCollection;
    }

    public function build(
        Product $product,
        Store $store
    ) {
        $nostoProduct = new NostoProduct();
        $nostoProduct->setProductId((string)$product->getId());
        $nostoProduct->setUrl($this->urlBuilder->getUrlInStore($product, $store));
        if ($this->nostoDataHelper->isInventoryTaggingEnabled($store)) {
            $inventoryLevel = $this->stockService->getQuantity($product, $store);
            $nostoProduct->setInventoryLevel($inventoryLevel);
        }
        $nostoProduct->setAvailability($this->fullProductBuilder->buildAvailability($product, $store));
        if ($this->nostoDataHelper->isVariationTaggingEnabled($store)) {
            // We need the full set of SKU's here, otherwise Nosto will remove the SKU's from the product
            $nostoProduct->setSkus($this->skuCollection->build($product, $store));
        }
        return $nostoProduct;
    }
}
