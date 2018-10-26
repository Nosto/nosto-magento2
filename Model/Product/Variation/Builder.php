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

namespace Nosto\Tagging\Model\Product\Variation;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Variation;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Customer\Model\Data\Group;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory as PriceFactory;
use Nosto\Object\Product\Product as NostoProduct;

class Builder
{
    private $nostoPriceHelper;
    private $eventManager;
    private $logger;
    private $nostoCurrencyHelper;
    private $priceFactory;

    /**
     * Builder constructor.
     *
     * @param NostoPriceHelper $priceHelper
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param PriceFactory $priceFactory
     */
    public function __construct(
        NostoPriceHelper $priceHelper,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        CurrencyHelper $nostoCurrencyHelper,
        PriceFactory $priceFactory
    ) {
        $this->nostoPriceHelper = $priceHelper;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->priceFactory = $priceFactory;
    }

    /**
     * @param Product $product
     * @param NostoProduct $
     * @param $nostoProduct
     * @param Store $store
     * @param Group $group
     * @return Variation
     */
    public function build(
        Product $product,
        NostoProduct $nostoProduct,
        Store $store,
        Group $group
    ) {
        $variation = new Variation();
        try {
            $variation->setVariationId($group->getCode());
            $variation->setAvailability($nostoProduct->getAvailability());

            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $product->setPrice($this->getVariationPrice($product, $group));
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );

            $variation->setPrice($price);
            $variation->setListPrice($listPrice);
            $variation->setPriceCurrencyCode($nostoProduct->getPriceCurrencyCode());
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch(
            'nosto_variation_load_after',
            [
                'variation' => $variation,
                'magentoProduct' => $product
            ]
        );
        return $variation;
    }

    /**
     * @param Product $product
     * @param Group $group
     * @return float
     */
    private function getVariationPrice(Product $product, Group $group)
    {
        $productPrices = $product->getTierPrices();
        if (!$productPrices) {
            return $product->getFinalPrice();
        }
        foreach ($productPrices as $price) {
            if ($price->getCustomerGroupId() === $group->getId()) {
                return $price->getValue();
            }
        }
        return $product->getFinalPrice();
    }
}
