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

namespace Nosto\Tagging\Helper;

use Magento\Bundle\Model\Product\Price as BundlePrice;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Price helper used for product price related tasks.
 */
class Price extends AbstractHelper
{
    private $catalogHelper;
    private $directoryHelper;
    /**
     * @var CurrencyFactory
     */
    private $currencyFactory;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param CatalogHelper $catalogHelper the catalog helper.
     * @param DirectoryHelper $directoryHelper
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        DirectoryHelper $directoryHelper,
        CurrencyFactory $currencyFactory
    ) {
        parent::__construct($context);

        $this->catalogHelper = $catalogHelper;
        $this->directoryHelper = $directoryHelper;
        $this->currencyFactory = $currencyFactory;
    }

    /**
     * Gets the unit price for a product model including taxes.
     *
     * @param $store
     * @param Product $product the product model.
     * @return float
     */
    public function getProductPriceInclTax(StoreInterface $store, Product $product)
    {
        return $this->convertToBaseCurrency($store, $this->getProductPrice($product, false, true));
    }

    /**
     * Get unit/final price for a product model.
     *
     * @param Product $product the product model.
     * @param bool $finalPrice if final price.
     * @param bool $inclTax if tax is to be included.
     * @return float
     * @suppress PhanTypeMismatchArgument
     */
    public function getProductPrice($product, $finalPrice = false, $inclTax = true)
    {
        switch ($product->getTypeId()) {
            // Get the bundle product "from" price.
            case ProductType::TYPE_BUNDLE:
                $priceModel = $product->getPriceModel();
                if ($priceModel instanceof BundlePrice) {
                    $price = $priceModel->getTotalPrices($product, 'min', $inclTax);
                } else {
                    $price = null;
                }
                break;

            // No constant for this value was found (Magento ver. 1.0.0-beta).
            // Get the grouped product "minimal" price.
            case 'grouped':
                $typeInstance = $product->getTypeInstance();
                if ($typeInstance instanceof GroupedType) {
                    $associatedProducts = $typeInstance
                        ->setStoreFilter($product->getStore(), $product)
                        ->getAssociatedProducts($product);
                    $cheapestAssociatedProduct = null;
                    $minimalPrice = 0;
                    foreach ($associatedProducts as $associatedProduct) {
                        /** @var Product $associatedProduct */
                        $tmpPrice = $finalPrice
                            ? $associatedProduct->getFinalPrice()
                            : $associatedProduct->getPrice();
                        if ($minimalPrice === 0 || $minimalPrice > $tmpPrice) {
                            $minimalPrice = $tmpPrice;
                            $cheapestAssociatedProduct = $associatedProduct;
                        }
                    }
                    $price = $minimalPrice;
                    if ($inclTax && $cheapestAssociatedProduct !== null) {
                        $price = $this->catalogHelper->getTaxPrice($cheapestAssociatedProduct,
                            $price, true);
                    }
                } else {
                    $price = null;
                }
                break;

            // No constant for this value was found (Magento ver. 1.0.0-beta).
            // The configurable product has the tax already applied in the
            // "final" price, but not in the regular price.
            case 'configurable':
                if ($finalPrice) {
                    $price = $product->getFinalPrice();
                } elseif ($inclTax) {
                    $price = $this->catalogHelper->getTaxPrice($product, $product->getPrice(),
                        true);
                } else {
                    $price = $product->getPrice();
                }
                break;

            default:
                $price = $finalPrice ? $product->getFinalPrice() : $product->getPrice();
                if ($inclTax) {
                    $price = $this->catalogHelper->getTaxPrice($product, $price, true);
                }
                break;
        }

        return $price;
    }

    /**
     * Get the final price for a product model including taxes.
     *
     * @param StoreInterface|Store $store
     * @param Product $product the product model.
     * @return float
     */
    public function getProductFinalPriceInclTax(StoreInterface $store, Product $product)
    {
        return $this->convertToBaseCurrency($store, $this->getProductPrice($product, true, true));
    }

    /**
     * Helper method that converts a price from the current currency to the store's base currency.
     * If the currency currency is the same as the store's base currency, the same value is
     * automatically returned.
     *
     * @param StoreInterface|Store $store the store to whose base currency to convert the amount to
     * @param float $amount the amount that should be converted to the store's base currency
     * @return float the amount converted into the store's base currency
     */
    public function convertToBaseCurrency(StoreInterface $store, $amount)
    {
        $rateToBase = $this->currencyFactory->create()->load($store->getCurrentCurrencyCode())->getAnyRate($store->getBaseCurrencyCode());
        var_dump($amount);
        var_dump($rateToBase);
        return $amount * $rateToBase;
    }
}
