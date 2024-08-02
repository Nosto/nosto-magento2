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

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Exception\NonBuildableProductException;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Tagging\Model\Product\Variation\Collection as PriceVariationCollection;

class PriceProduct
{
    /** @var NostoDataHelper */
    private NostoDataHelper $nostoDataHelper;

    /** @var NostoPriceHelper */
    private NostoPriceHelper $nostoPriceHelper;

    /** @var NostoUrlBuilder */
    private NostoUrlBuilder $urlBuilder;

    /** @var CurrencyHelper */
    private CurrencyHelper $nostoCurrencyHelper;

    /** @var PriceVariationCollection */
    private PriceVariationCollection $priceVariationCollection;



    /**
     * Builder constructor.
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoPriceHelper $priceHelper
     * @param NostoUrlBuilder $urlBuilder
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param PriceVariationCollection $priceVariationCollection
     */
    public function __construct(
        NostoDataHelper $nostoDataHelper,
        NostoPriceHelper $priceHelper,
        NostoUrlBuilder $urlBuilder,
        CurrencyHelper $nostoCurrencyHelper,
        PriceVariationCollection $priceVariationCollection,
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoPriceHelper = $priceHelper;
        $this->urlBuilder = $urlBuilder;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->priceVariationCollection = $priceVariationCollection;
    }

    public function build(
        Product $product,
        Store $store
    ) {
        $nostoProduct = new NostoProduct();
        $nostoProduct->setProductId((string)$product->getId());
        $nostoProduct->setUrl($this->urlBuilder->getUrlInStore($product, $store));
        try {
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            if ($this->nostoDataHelper->isPricingVariationEnabled($store)
                && $this->nostoDataHelper->isMultiCurrencyDisabled($store)
            ) {
                $nostoProduct->setVariations(
                    $this->priceVariationCollection->build($product, $nostoProduct, $store)
                );
            }
            $nostoProduct->setPrice($price);
                $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                    $this->nostoPriceHelper->getProductDisplayPrice(
                        $product,
                        $store
                    ),
                    $store
                );
                $nostoProduct->setListPrice($listPrice);
        } catch (Exception $e) {
            $msg = sprintf("Could not set price for partial product product with id: %s", $product->getId());
            throw new NonBuildableProductException($msg, $e);
        }
        return $nostoProduct;
    }
}