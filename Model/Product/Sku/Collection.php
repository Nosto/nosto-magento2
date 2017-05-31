<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product\Sku;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\Product\SkuCollection;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Model\Product\Sku\Builder as NostoSkuBuilder;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

class Collection
{
    private $configurableType;
    private $nostoHelperSentry;
    private $nostoHelperData;
    private $nostoPriceHelper;
    private $nostoSkuBuilder;

    /**
     * Builder constructor.
     * @param NostoHelperSentry $nostoHelperSentry
     * @param ConfigurableType $configurableType
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param Builder $nostoSkuBuilder
     */
    public function __construct(
        NostoHelperSentry $nostoHelperSentry,
        ConfigurableType $configurableType,
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoSkuBuilder $nostoSkuBuilder
    ) {
        $this->configurableType = $configurableType;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoSkuBuilder = $nostoSkuBuilder;
        $this->nostoHelperSentry = $nostoHelperSentry;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return SkuCollection
     */
    public function build(Product $product, Store $store)
    {
        $skuCollection = new SkuCollection();
        if ($product->getTypeId() === ConfigurableType::TYPE_CODE) {
            $attributes = $this->configurableType->getConfigurableAttributes($product);
            /** @var Product $product */
            foreach ($this->configurableType->getUsedProducts($product) as $product) {
                try {
                    $sku = $this->nostoSkuBuilder->build($product, $store, $attributes);
                    $skuCollection->append($sku);
                } catch (NostoException $e) {
                    $this->nostoHelperSentry->error($e);
                }
            }
        }

        return $skuCollection;
    }
}
