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

namespace Nosto\Tagging\Model\Product\Variation;

use Exception;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory as PriceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product as MageProduct;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Customer\Model\Data\Group;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\Variation;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Sku as SkuResource;

class Builder
{
    private NostoPriceHelper $nostoPriceHelper;
    private ManagerInterface $eventManager;
    private NostoLogger $logger;
    private CurrencyHelper $nostoCurrencyHelper;
    private PriceFactory $priceFactory;
    private RuleResourceModel $ruleResourceModel;
    private NostoProductRepository $nostoProductRepository;
    private TimezoneInterface $localeDate;
    private SkuResource $skuResource;

    /**
     * Builder constructor.
     * @param NostoPriceHelper $priceHelper
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param PriceFactory $priceFactory
     * @param RuleResourceModel $ruleResourceModel
     * @param NostoProductRepository $nostoProductRepository
     * @param TimezoneInterface $localeDate
     * @param SkuResource $skuResource
     */
    public function __construct(
        NostoPriceHelper $priceHelper,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        CurrencyHelper $nostoCurrencyHelper,
        PriceFactory $priceFactory,
        RuleResourceModel $ruleResourceModel,
        NostoProductRepository $nostoProductRepository,
        TimezoneInterface $localeDate,
        SkuResource $skuResource
    ) {
        $this->nostoPriceHelper = $priceHelper;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->priceFactory = $priceFactory;
        $this->ruleResourceModel = $ruleResourceModel;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->localeDate = $localeDate;
        $this->skuResource = $skuResource;
    }

    /**
     * @param Product $product
     * @param NostoProduct $nostoProduct
     * @param Store $store
     * @param Group $group
     * @param array $reloadedSkuCache
     * @return Variation
     */
    public function build(
        Product $product,
        NostoProduct $nostoProduct,
        Store $store,
        Group $group,
        array &$reloadedSkuCache = []
    ) {
        $variation = new Variation();
        try {
            $variation->setVariationId($group->getCode());
            $variation->setAvailability($nostoProduct->getAvailability());
            $variation->setPrice($this->getLowestVariationPrice($product, $group, $store, $reloadedSkuCache));
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $variation->setListPrice($listPrice);
            $variation->setPriceCurrencyCode($nostoProduct->getPriceCurrencyCode());
        } catch (Exception $e) {
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
     * @param Store $store
     * @param array $reloadedSkuCache
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException|NostoException
     */
    private function getLowestVariationPrice(
        Product $product,
        Group $group,
        Store $store,
        array &$reloadedSkuCache
    ) {
        // If product is configurable, the parent has no customer group price. Get SKU with lowest price
        if ($product->getTypeInstance() instanceof ConfigurableType) {
            $product = $this->getMinPriceSku($product, $group, $store, $reloadedSkuCache);
        }

        // Only returns the SKU price if it's lower than final price
        // Merchant can have a fixed customer group price that is higher than the product
        // price with a catalog price discount rule applied.
        // This is normal Magento 2 behaviour
        $productTierPriceInterfaces = $product->getTierPrices();
        foreach ($productTierPriceInterfaces as $price) {
            if ($price->getCustomerGroupId() === $group->getId()
                && $price->getValue() < $product->getFinalPrice()
            ) {
                return $this->nostoPriceHelper->addTaxDisplayPriceIfApplicable(
                    $product,
                    $store,
                    $price->getValue()
                );
            }
        }

        $rulePrice = $this->ruleResourceModel->getRulePrice(
            $this->localeDate->scopeDate(),
            $store->getWebsiteId(),
            $group->getId(),
            $product->getId()
        );

        if ($rulePrice) {
            return $this->nostoPriceHelper->addTaxDisplayPriceIfApplicable(
                $product,
                $store,
                $rulePrice
            );
        }

        // If no tier prices, there's no customer group pricing for this product
        // or it's higher than final price with catalog price rule discount
        return $this->nostoPriceHelper->getProductPrice($product, $store);
    }

    /**
     * Returns the SKU|Product object with the lowest price.
     *
     * Reads final_price from catalog_product_index_price for the given customer group
     * to identify the cheapest SKU without loading all child product objects.
     *
     * $reloadedSkuCache memoizes reloadProduct() results by "skuId:storeId" for the
     * duration of a single Variation\Collection::build() call (one call per customer
     * group in that loop), so the same winning SKU is only force-reloaded once even
     * when multiple groups resolve to it. Callers that don't need this (e.g. direct
     * unit tests) may omit it — it defaults to a fresh, throwaway array.
     *
     * @param MageProduct $product
     * @param Group $group
     * @param Store $store
     * @param array $reloadedSkuCache
     * @return MageProduct
     * @throws NoSuchEntityException
     */
    public function getMinPriceSku(
        Product $product,
        Group $group,
        Store $store,
        array &$reloadedSkuCache = []
    ): MageProduct {
        if (!$product->getTypeInstance() instanceof ConfigurableType) {
            return $product;
        }
        $skuIds = $this->nostoProductRepository->getSkuIds($product);
        if (empty($skuIds)) {
            return $product;
        }
        $minPriceSkuId = $this->skuResource->getMinPriceSkuId(
            $store->getWebsite(),
            (int)$group->getId(),
            array_values($skuIds)
        );
        if ($minPriceSkuId === null) {
            return $this->getMinPriceSkuFallback($product, $group, $store);
        }
        $cacheKey = $minPriceSkuId . ':' . (int)$store->getId();
        if (!array_key_exists($cacheKey, $reloadedSkuCache)) {
            $reloadedSkuCache[$cacheKey] = $this->nostoProductRepository->reloadProduct(
                $minPriceSkuId,
                (int)$store->getId()
            );
        }
        return $reloadedSkuCache[$cacheKey];
    }

    /**
     * Fallback when the price index has no rows for the requested website/customer group.
     * Loads in-stock child product models and picks the one with the lowest effective price
     * (base price, catalog rule price, and customer-group tier price all considered).
     *
     * @param MageProduct $product
     * @param Group $group
     * @param Store $store
     * @return MageProduct
     * @throws NoSuchEntityException
     */
    private function getMinPriceSkuFallback(Product $product, Group $group, Store $store): MageProduct
    {
        $skus = $this->nostoProductRepository->getInStockSkuProducts($product, $store);
        if (empty($skus)) {
            return $product;
        }
        $minPriceSku = null;
        $minPrice = PHP_INT_MAX;
        foreach ($skus as $sku) {
            $skuPrice = (float)$sku->getPrice();
            foreach ($sku->getTierPrices() as $tierPrice) {
                if ((int)$tierPrice->getCustomerGroupId() === (int)$group->getId()) {
                    $skuPrice = min($skuPrice, (float)$tierPrice->getValue());
                    break;
                }
            }
            $rulePrice = $this->ruleResourceModel->getRulePrice(
                $this->localeDate->scopeDate(),
                $store->getWebsiteId(),
                $group->getId(),
                $sku->getId()
            );
            if ($rulePrice !== false) {
                $skuPrice = min($skuPrice, (float)$rulePrice);
            }
            if ($skuPrice < $minPrice) {
                $minPrice = $skuPrice;
                $minPriceSku = $sku;
            }
        }
        return $minPriceSku ?? $product;
    }
}
