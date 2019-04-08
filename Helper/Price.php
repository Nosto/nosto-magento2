<?php
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

namespace Nosto\Tagging\Helper;

use Magento\Bundle\Model\Product\Price as BundlePrice;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Price helper used for product price related tasks.
 */
class Price extends AbstractHelper
{
    private $catalogHelper;
    private $priceRuleFactory;
    private $localeDate;
    private $nostoProductRepository;
    private $taxHelper;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param CatalogHelper $catalogHelper the catalog helper.
     * @param RuleFactory $ruleFactory
     * @param TimezoneInterface $localeDate
     * @param NostoProductRepository $nostoProductRepository
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        RuleFactory $ruleFactory,
        TimezoneInterface $localeDate,
        NostoProductRepository $nostoProductRepository,
        TaxHelper $taxHelper
    ) {
        parent::__construct($context);
        $this->catalogHelper = $catalogHelper;
        $this->priceRuleFactory = $ruleFactory;
        $this->localeDate = $localeDate;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->taxHelper = $taxHelper;
    }

    /**
     * Gets the unit price for a product model including taxes.
     *
     * @param Product $product the product model.
     * @param Store $store
     * @return float
     * @throws LocalizedException
     */
    public function getProductDisplayPrice(Product $product, Store $store)
    {
        return $this->getProductPrice(
            $product,
            $store,
            $this->includeTaxes($store),
            false
        );
    }

    /**
     * Get unit/final price for a product model.
     *
     * @param Product $product the product model.
     * @param Store $store
     * @param bool $inclTax if tax is to be included.
     * @param bool $finalPrice if final price.
     * @return float
     * @suppress PhanTypeMismatchArgument
     * @suppress PhanDeprecatedFunction
     * @throws LocalizedException
     */
    public function getProductPrice(// @codingStandardsIgnoreLine
        Product $product,
        Store $store,
        $inclTax = true,
        $finalPrice = false
    ) {
        switch ($product->getTypeId()) {
            // Get the bundle product "from" price.
            case BundleType::TYPE_CODE:
                $price = $this->getBundleProductPrice($product, $finalPrice, $inclTax, $store);
                break;

            // Get the grouped product "minimal" price.
            case GroupedType::TYPE_CODE:
                $price = $this->getGroupedProductPrice($product, $finalPrice, $inclTax);
                break;

            // We will use the SKU that has the lowest final price
            case ConfigurableType::TYPE_CODE:
                $price = $this->getConfigurableProductPrice($product, $finalPrice, $inclTax, $store);
                break;

            default:
                $date = $this->localeDate->scopeDate();
                $wid = $product->getStore()->getWebsiteId();
                $gid = GroupManagement::NOT_LOGGED_IN_ID;
                $pid = $product->getId();
                if ($finalPrice) {
                    $currentProductPrice = $product->getFinalPrice();
                    /** @noinspection UnnecessaryCastingInspection */
                    $pricesToCompare = [(float)$currentProductPrice, (float)$product->getPrice()];
                    foreach ($product->getTierPrices() as $tierPrice) {
                        if ((int)$tierPrice->getCustomerGroupId() === $gid) {
                            $pricesToCompare[] = $tierPrice->getValue();
                            break;
                        }
                    }
                    try {
                        $currentRulePrice = $this->priceRuleFactory->create()->getRulePrice($date, $wid, $gid, $pid);
                    } catch (\Exception $e) {
                        $currentRulePrice = $product->getFinalPrice();
                    }
                    if (is_numeric($currentRulePrice)) {
                        $pricesToCompare[] = $currentRulePrice;
                    }
                    $price = min($pricesToCompare);
                } else {
                    $price = $product->getPrice();
                }
                if ($inclTax) {
                    $price = $this->catalogHelper->getTaxPrice(
                        $product,
                        $price,
                        true,
                        null,
                        null,
                        null,
                        $store
                    );
                }
                break;
        }

        return $price;
    }

    /**
     * Get the final price for a product model including taxes.
     *
     * @param Product $product the product model.
     * @param Store $store
     * @return float
     * @throws LocalizedException
     */
    public function getProductFinalDisplayPrice(Product $product, Store $store)
    {
        return $this->getProductPrice(
            $product,
            $store,
            $this->includeTaxes($store),
            true
        );
    }

    /**
     * Tells if taxes should be added to the prices.
     * We need this method due to the bugs in Magento's store emulation that
     * are not setting the tax display settings correctly for the API calls.
     *
     * If the store is configured to show prices with and without taxes we will
     * use the price without taxes.
     *
     * @param Store $store
     * @return bool
     */
    private function includeTaxes(Store $store)
    {
        return ($this->taxHelper->getPriceDisplayType($store) === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX);
    }

    /**
     * Calculates the price for Product of type Bundle
     *
     * @param Product $product
     * @param $finalPrice
     * @param $inclTax
     * @param Store $store
     * @return array|float|int|mixed
     * @throws LocalizedException
     */
    private function getBundleProductPrice(Product $product, $finalPrice, $inclTax, Store $store)
    {
        $priceModel = $product->getPriceModel();
        $price = 0.0;
        if (!$priceModel instanceof BundlePrice) {
            return $price;
        }
        $productType = $product->getTypeInstance();
        if ($finalPrice) {
            $price = $priceModel->getTotalPrices(
                $product,
                'min',
                $inclTax
            );
        } elseif ($productType instanceof BundleType) {
            $options = $productType->getOptions($product);
            $allOptional = true;
            $minPrices = [];
            $requiredMinPrices = [];
            /** @var \Magento\Bundle\Model\Option $option */
            foreach ($options as $option) {
                $selectionMinPrice = null;
                $optionSelections = $option->getSelections();
                if ($optionSelections === null) {
                    continue;
                }
                foreach ($optionSelections as $selection) {
                    /** @var Product $selection */
                    $selectionPrice
                        = $this->getProductDisplayPrice($selection, $store);
                    if ($selectionMinPrice === null
                        || $selectionPrice < $selectionMinPrice
                    ) {
                        $selectionMinPrice = $selectionPrice;
                    }
                }
                $minPrices[] = $selectionMinPrice;
                if ($option->getRequired()) {
                    $allOptional = false;
                    $requiredMinPrices[] = $selectionMinPrice;
                }
            }
            // If all products are optional use the price for the cheapest option
            $price = $allOptional ? min($minPrices) : array_sum($requiredMinPrices);
        }
        return $price;
    }

    /**
     * Calculates the price for Product of type Grouped
     *
     * @param Product $product
     * @param $finalPrice
     * @param $inclTax
     * @return float
     */
    private function getGroupedProductPrice(Product $product, $finalPrice, $inclTax)
    {
        $price = 0.0;
        $typeInstance = $product->getTypeInstance();
        if (!$typeInstance instanceof GroupedType) {
            return $price;
        }
        $associatedProducts = $typeInstance
            ->setStoreFilter($product->getStore(), $product)
            ->getAssociatedProducts($product);
        $cheapestAssociatedProduct = null;
        $minimalPrice = 0.0;
        foreach ($associatedProducts as $associatedProduct) {
            /** @var Product $associatedProduct */
            $tmpPrice = $finalPrice
                ? $associatedProduct->getFinalPrice()
                : $associatedProduct->getPrice();
            if ($minimalPrice === 0.0 || $minimalPrice > $tmpPrice) {
                $minimalPrice = $tmpPrice;
                $cheapestAssociatedProduct = $associatedProduct;
            }
        }
        $price = $minimalPrice;
        if ($inclTax && $cheapestAssociatedProduct !== null) {
            $price = $this->catalogHelper->getTaxPrice(
                $cheapestAssociatedProduct,
                $price,
                true
            );
        }
        return $price;
    }

    /**
     * Calculates the price for Product of type Configurable
     *
     * @param Product $product
     * @param $finalPrice
     * @param $inclTax
     * @param Store $store
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getConfigurableProductPrice(Product $product, $finalPrice, $inclTax, Store $store)
    {
        $price = 0.0;
        if (!$product->getTypeInstance() instanceof ConfigurableType) {
            return $price;
        }
        $products = $this->nostoProductRepository->getSkus($product);
        $skus = [];
        $finalPrices = [];
        $outOfStockFinalPrices = [];
        foreach ($products as $sku) {
            if (!$sku instanceof Product) {
                continue;
            }
            if (!$sku->isDisabled() && $sku->isAvailable()) {
                $finalPrices[$sku->getId()] = $this->getProductPrice(
                    $sku,
                    $store,
                    $inclTax,
                    true
                );
            } elseif (empty($finalPrices)) {
                $outOfStockFinalPrices[$sku->getId()] = $this->getProductPrice(
                    $sku,
                    $store,
                    $inclTax,
                    true
                );
            }
            $skus[$sku->getId()] = $sku;
        }
        // If none of the SKU's are available, use the unavailable ones
        $finalPrices = empty($finalPrices) ? $outOfStockFinalPrices : $finalPrices;
        asort($finalPrices, SORT_NUMERIC);
        $keys = array_keys($finalPrices);
        if (!empty($keys[0]) && !empty($skus[$keys[0]])) {
            $simpleProduct = $skus[$keys[0]];
            if ($finalPrice) {
                $price = $this->getProductFinalDisplayPrice($simpleProduct, $store);
            } else {
                $price = $this->getProductDisplayPrice($simpleProduct, $store);
            }
        }
        return $price;
    }
}
