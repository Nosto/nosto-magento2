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

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Framework\Event\ManagerInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Helper\Stock as NostoStockHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;
use Nosto\Tagging\Model\Product\Tags\LowStock as LowStockHelper;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Types\Product\ProductInterface;
use Nosto\Object\ModelFilter;

class Builder
{
    use BuilderTrait {
        BuilderTrait::__construct as builderTraitConstruct; // @codingStandardsIgnoreLine
    }

    const CUSTOMIZED_TAGS = ['tag1', 'tag2', 'tag3'];
    const NOSTO_SCOPE_TAGGING = 'tagging';
    const NOSTO_SCOPE_API = 'api';

    private $nostoDataHelper;
    private $nostoPriceHelper;
    private $nostoCategoryBuilder;
    private $nostoStockHelper;
    private $galleryReadHandler;
    private $eventManager;
    private $logger;
    private $reviewFactory;
    private $urlBuilder;
    private $skuCollection;
    private $nostoCurrencyHelper;
    private $lowStockHelper;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoCategoryBuilder $categoryBuilder
     * @param NostoStockHelper $stockHelper
     * @param NostoSkuCollection $skuCollection
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param ReviewFactory $reviewFactory
     * @param GalleryReadHandler $galleryReadHandler
     * @param NostoUrlBuilder $urlBuilder
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param LowStockHelper $lowStockHelper
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoCategoryBuilder $categoryBuilder,
        NostoStockHelper $stockHelper,
        NostoSkuCollection $skuCollection,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        ReviewFactory $reviewFactory,
        GalleryReadHandler $galleryReadHandler,
        NostoUrlBuilder $urlBuilder,
        CurrencyHelper $nostoCurrencyHelper,
        LowStockHelper $lowStockHelper
    ) {
        $this->nostoDataHelper = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoCategoryBuilder = $categoryBuilder;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoStockHelper = $stockHelper;
        $this->reviewFactory = $reviewFactory;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->urlBuilder = $urlBuilder;
        $this->skuCollection = $skuCollection;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->lowStockHelper = $lowStockHelper;
        $this->builderTraitConstruct($nostoHelperData, $logger);
    }

    /**
     * @param Product $product
     * @param Store $store
     * @param string $nostoScope
     * @return NostoProduct|null
     * @throws \Exception
     */
    public function build(
        Product $product,
        Store $store,
        $nostoScope = self::NOSTO_SCOPE_API
    ) {
        $nostoProduct = new NostoProduct();
        $modelFilter = new ModelFilter();

        $this->eventManager->dispatch(
            'nosto_product_load_before',
            ['product' => $nostoProduct, 'magentoProduct' => $product, 'modelFilter' => $modelFilter]
        );
        if (!$modelFilter->isValid()) {
            return null;
        }
        try {
            $nostoProduct->setUrl($this->urlBuilder->getUrlInStore($product, $store));
            $nostoProduct->setProductId((string)$product->getId());
            $nostoProduct->setName($product->getName());
            $nostoProduct->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $nostoProduct->setPrice($price);
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $nostoProduct->setListPrice($listPrice);
            $nostoProduct->setPriceCurrencyCode(
                $this->nostoCurrencyHelper->getTaggingCurrency(
                    $store
                )->getCode()
            );

            if ($this->nostoCurrencyHelper->exchangeRatesInUse($store)) {
                $nostoProduct->setVariationId(
                    $this->nostoCurrencyHelper->getTaggingCurrency(
                        $store
                    )->getCode()
                );
            }

            $nostoProduct->setAvailability($this->buildAvailability($product));
            $nostoProduct->setCategories($this->nostoCategoryBuilder->buildCategories($product));
            if ($nostoScope == self::NOSTO_SCOPE_API
                && $this->nostoDataHelper->isInventoryTaggingEnabled($store)
            ) {
                $nostoProduct->setInventoryLevel($this->nostoStockHelper->getQty($product));
            }
            if ($this->nostoDataHelper->isRatingTaggingEnabled($store)) {
                $nostoProduct->setRatingValue($this->buildRatingValue($product, $store));
                $nostoProduct->setReviewCount($this->buildReviewCount($product, $store));
            }
            if ($this->nostoDataHelper->isAltimgTaggingEnabled($store)) {
                $nostoProduct->setAlternateImageUrls($this->buildAlternativeImages($product, $store));
            }
            if ($this->nostoDataHelper->isVariationTaggingEnabled($store)) {
                $nostoProduct->setSkus($this->skuCollection->build($product, $store));
            }
            $descriptions = [];
            if ($product->hasData('short_description')) {
                $descriptions[] = $product->getData('short_description');
            }
            if ($product->hasData('description')) {
                $descriptions[] = $product->getData('description');
            }
            if (!empty($descriptions)) {
                $nostoProduct->setDescription(implode(' ', $descriptions));
            }
            $brandAttribute = $this->nostoDataHelper->getBrandAttribute($store);
            if ($product->hasData($brandAttribute)) {
                $nostoProduct->setBrand($this->getAttributeValue($product, $brandAttribute));
            }
            $marginAttribute = $this->nostoDataHelper->getMarginAttribute($store);
            if ($nostoScope === self::NOSTO_SCOPE_API
                && $product->hasData($marginAttribute)
            ) {
                $nostoProduct->setSupplierCost($this->getAttributeValue($product, $marginAttribute));
            }
            $gtinAttribute = $this->nostoDataHelper->getGtinAttribute($store);
            if ($product->hasData($gtinAttribute)) {
                $nostoProduct->setGtin($this->getAttributeValue($product, $marginAttribute));
            }
            if (($tags = $this->buildTags($product, $store)) !== []) {
                $nostoProduct->setTag1($tags);
            }

            $nostoProduct->setCustomFields($this->buildCustomFields($product, $store));

            //update customized tag1, Tag2 and Tag3
            $this->amendAttributeTags($product, $nostoProduct, $store);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
        $this->eventManager->dispatch(
            'nosto_product_load_after',
            ['product' => $nostoProduct, 'magentoProduct' => $product, 'modelFilter' => $modelFilter]
        );

        if (!$modelFilter->isValid()) {
            return null;
        }
        return $nostoProduct;
    }

    /**
     * Amends the product attributes to tags array if attributes are defined
     * and are present in product
     *
     * @param Product $product the magento product model.
     * @param NostoProduct $nostoProduct nosto product object
     * @param Store $store the store model.
     */
    private function amendAttributeTags(Product $product, NostoProduct $nostoProduct, Store $store)
    {
        foreach (self::CUSTOMIZED_TAGS as $tag) {
            $attributes = $this->nostoDataHelper->getTagAttributes($tag, $store);
            if (!$attributes) {
                continue;
            }
            foreach ($attributes as $productAttribute) {
                try {
                    $attributeValue = $this->getAttributeValue($product, $productAttribute);
                    if (empty($attributeValue)) {
                        continue;
                    }
                    //addTag1(), addTag2() and addTag3() are called
                    $addTagMethodName = 'add' . $tag;
                    $nostoProduct->$addTagMethodName(sprintf('%s:%s', $productAttribute, $attributeValue));
                } catch (\Exception $e) {
                    $this->logger->exception($e);
                }
            }
        }
    }

    /**
     * Generates the availability for the product
     *
     * @param Product $product
     * @return string
     */
    private function buildAvailability(Product $product)
    {
        $availability = ProductInterface::OUT_OF_STOCK;
        if (!$product->isVisibleInSiteVisibility()) {
            $availability = ProductInterface::INVISIBLE;
        } elseif ($product->isAvailable()) {
            $availability = ProductInterface::IN_STOCK;
        }

        return $availability;
    }

    /**
     * Helper method to fetch and return the normalised rating value for a product. The rating is
     * normalised to a 0-5 value.
     *
     * @param Product $product the product whose rating value to fetch
     * @param Store $store the store scope in which to fetch the rating
     * @return float|null the normalized rating value of the product
     */
    private function buildRatingValue(Product $product, Store $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        if ($product->getRatingSummary()->getReviewsCount() > 0) {
            /** @noinspection PhpUndefinedMethodInspection */
            return round($product->getRatingSummary()->getRatingSummary() / 20, 1);
        }
        return null;
    }

    /**
     * Helper method to fetch and return the total review count for a product. The review counts are
     * returned as is.
     *
     * @param Product $product the product whose rating value to fetch
     * @param Store $store the store scope in which to fetch the rating
     * @return int|null the normalized rating value of the product
     */
    private function buildReviewCount(Product $product, Store $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        if ($product->getRatingSummary()->getReviewsCount() > 0) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $product->getRatingSummary()->getReviewsCount();
        }
        return null;
    }

    /**
     * Adds the alternative image urls
     *
     * @param Product $product the product model.
     * @param Store $store
     * @return array
     */
    public function buildAlternativeImages(Product $product, Store $store)
    {
        $images = [];
        $this->galleryReadHandler->execute($product);
        foreach ($product->getMediaGalleryImages() as $image) {
            if (isset($image['url']) && (isset($image['disabled']) && $image['disabled'] !== '1')) {
                $images[] = $this->finalizeImageUrl($image['url'], $store);
            }
        }

        return $images;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return array
     */
    public function buildTags(Product $product, Store $store)
    {
        $tags = [];

        if (!$product->canConfigure()) {
            $tags[] = ProductInterface::ADD_TO_CART;
        }

        if ($this->nostoDataHelper->isLowStockIndicationEnabled($store)
            && $this->lowStockHelper->build($product)
        ) {
            $tags[] = ProductInterface::LOW_STOCK;
        }

        return $tags;
    }

    /**
     * Builds a product with required info for deletion
     *
     * @param int $productId
     * @return NostoProduct
     */
    public function buildForDeletion($productId)
    {
        $nostoProduct = new NostoProduct();
        $nostoProduct->setProductId((string)$productId);
        $nostoProduct->setAvailability(ProductInterface::DISCONTINUED);

        return $nostoProduct;
    }
}
