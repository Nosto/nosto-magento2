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
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Model\ProductFactory;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

/**
 * Price helper used for product price related tasks.
 */
class Price extends AbstractHelper
{
    private $catalogHelper;
    private $productFactory;
    private $nostoHelperSentry;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param CatalogHelper $catalogHelper the catalog helper.
     * @param ProductFactory $productFactory
     * @param NostoHelperSentry $nostoHelperSentry
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        ProductFactory $productFactory,
        NostoHelperSentry $nostoHelperSentry
    ) {
        parent::__construct($context);
        $this->catalogHelper = $catalogHelper;
        $this->productFactory = $productFactory;
        $this->nostoHelperSentry = $nostoHelperSentry;
    }

    /**
     * Gets the unit price for a product model including taxes.
     *
     * @param Product $product the product model.
     * @return float
     */
    public function getProductPriceInclTax(Product $product)
    {
        $price = $this->getProductPrice($product, false, true);

        return $price;
    }

    /**
     * Get unit/final price for a product model.
     *
     * @param Product $product the product model.
     * @param bool $finalPrice if final price.
     * @param bool $inclTax if tax is to be included.
     * @return float
     * @suppress PhanTypeMismatchArgument
     * @suppress PhanDeprecatedFunction
     */
    public function getProductPrice(
        Product $product,
        $finalPrice = false,
        $inclTax = true
    ) {
        switch ($product->getTypeId()) {
            // Get the bundle product "from" price.
            case BundleType::TYPE_CODE:
                $priceModel = $product->getPriceModel();
                if ($priceModel instanceof BundlePrice) {
                    if ($finalPrice) {
                        $price = $priceModel->getTotalPrices(
                            $product,
                            'min',
                            $inclTax
                        );
                    } else {
                        $productType = $product->getTypeInstance();
                        $childProducts = $productType->getChildrenIds(
                            $product->getId()
                        );
                        $listPrice = 0;
                        foreach ($childProducts as $skuIds) {
                            if (is_array($skuIds)) {
                                try {
                                    $skuId = reset($skuIds);
                                    /** @noinspection PhpDeprecationInspection */
                                    $sku = $this->productFactory->create()->load(
                                        $skuId
                                    );
                                    $listPrice += $this->getProductPriceInclTax(
                                        $sku
                                    );
                                } catch (\Exception $e) {
                                    $this->nostoHelperSentry->error($e);
                                }
                            }
                        }
                        $price = $listPrice;
                    }
                } else {
                    $price = null;
                }
                break;

            // Get the grouped product "minimal" price.
            case GroupedType::TYPE_CODE:
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
                        $price = $this->catalogHelper->getTaxPrice(
                            $cheapestAssociatedProduct,
                            $price,
                            true
                        );
                    }
                } else {
                    $price = null;
                }
                break;

            // We will use the SKU that has the lowest final price
            case ConfigurableType::TYPE_CODE:
                $productType = $product->getTypeInstance();
                if ($productType instanceof ConfigurableType) {
                    $products = $productType->getUsedProducts($product);
                    $skus = [];
                    $finalPrices = [];
                    /** @var Product $sku */
                    foreach ($products as $sku) {
                        $finalPrices[$sku->getId()] = $this->getProductPrice(
                            $sku,
                            true,
                            true
                        );
                        $skus[$sku->getId()] = $sku;
                    }
                    asort($finalPrices, SORT_NUMERIC);
                    $min = array_keys($finalPrices)[0];
                    if (!empty($skus[$min])) {
                        $simpleProduct = $skus[$min];
                    } else { // Fallback to given product
                        $simpleProduct = $product;
                    }
                    if ($finalPrice) {
                        $price = $this->getProductFinalPriceInclTax($simpleProduct);
                    } elseif ($inclTax) {
                        $price = $this->getProductPriceInclTax($simpleProduct);
                    } else {
                        $price = $product->getPrice();
                    }
                } else {
                    $price = null;
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
     * @param Product $product the product model.
     * @return float
     */
    public function getProductFinalPriceInclTax(Product $product)
    {
        $price = $this->getProductPrice($product, true, true);

        return $price;
    }
}
