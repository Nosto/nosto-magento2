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

use Magento\Catalog\Helper\Catalog;
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
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleResourceModel;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Catalog\Model\Product as MageProduct;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Builder
{
    private $nostoPriceHelper;
    private $eventManager;
    private $logger;
    private $nostoCurrencyHelper;
    private $priceFactory;
    private $ruleResourceModel;
    private $mageDatetime;
    private $localeDate;

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
        PriceFactory $priceFactory,
        RuleResourceModel $ruleResourceModel,
        NostoProductRepository $nostoProductRepository,
        TimezoneInterface $localeDate
    ) {
        $this->nostoPriceHelper = $priceHelper;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->priceFactory = $priceFactory;
        $this->ruleResourceModel = $ruleResourceModel;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->localeDate = $localeDate;
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
            $variation->setPrice($this->getLowestVariationPrice($product, $group, $store));
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
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
    private function getLowestVariationPrice(Product $product, Group $group, Store $store)
    {
        // If product is configurable, the parent has no customer group price. Get SKU with lowest price
        if ($product->getTypeInstance() instanceof ConfigurableType) {
            $product = $this->getMinPriceSku($product, $group, $store);
        }
        // Only returns the SKU price if it's lower than final price
        // Merchant can have a fixed customer group price that is higher than the product
        // price with a catalog price discount rule applied.
        // This is normal Magento 2 behaviour
        foreach ($product->getTierPrices() as $price) {
            if ($price->getCustomerGroupId() === $group->getId()
                && $price->getValue() < $product->getFinalPrice()
            ) {
                return $price->getValue();
            }
        }
        // If no tier prices, there's no customer group pricing for this product
        // or it's higher than final price with catalog price rule discount
        return $product->getFinalPrice();
    }

    /**
     * Returns the SKU|Product object with the lowest price.
     *
     * @param MageProduct $product
     * @param Group $group
     * @param Store $store
     * @return MageProduct
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMinPriceSku(Product $product, Group $group, Store $store)
    {
        $minPriceSku = array();
        if (!$product->getTypeInstance() instanceof ConfigurableType) {
            return $product;
        }
        $skus = $this->nostoProductRepository->getSkus($product);
        if (empty($skus)) {
            return $product;
        }
        foreach ($skus as $sku) {
            if (!$sku instanceof MageProduct) {
                continue;
            }
            $skuPrice = $this->ruleResourceModel->getRulePrice(
                $this->localeDate->scopeDate(),
                $store->getWebsiteId(),
                $group->getId(),
                $sku->getId()
            );
            // If the SKU has customer group pricing for the not-logged-in group
            // and if this price is lower, use that price.
            foreach ($sku->getTierPrices() as $tierPrice) {
                if ((int)$tierPrice->getCustomerGroupId() === $group->getId()) {
                    $skuTierPrice = $tierPrice->getValue();
                }
            }
            // If has a customer group pricing for not_logged_in group,
            // check if it's lower than regular SKU price
            $skuPrice = (isset($skuTierPrice) && $skuTierPrice < $skuPrice)
                    ? $skuTierPrice
                    : $skuPrice;
            if (empty($minPriceSku)) { // First loop run
                $minPriceSku['sku'] = $sku;
                $minPriceSku['price'] = $skuPrice;
            } elseif ($skuPrice < $minPriceSku['price']) {
                $minPriceSku['sku'] = $sku;
                $minPriceSku['price'] = $skuPrice;
            }
        }
        return $minPriceSku['sku'];
    }

}
