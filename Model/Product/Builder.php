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

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Event\ManagerInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Helper\Stock as NostoStockHelper;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Types\Product\ProductInterface;
use Psr\Log\LoggerInterface;

class Builder
{
    const CUSTOMIZED_TAGS = ['Tag1', 'Tag2', 'Tag3'];

    private $nostoDataHelper;
    private $nostoPriceHelper;
    private $nostoCategoryBuilder;
    private $nostoStockHelper;
    private $categoryRepository;
    private $galleryReadHandler;
    private $eventManager;
    private $logger;
    private $reviewFactory;
    private $urlBuilder;
    private $skuCollection;
    private $nostoCurrencyHelper;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoCategoryBuilder $categoryBuilder
     * @param NostoStockHelper $stockHelper
     * @param NostoSkuCollection $skuCollection
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     * @param ReviewFactory $reviewFactory
     * @param GalleryReadHandler $galleryReadHandler
     * @param NostoUrlBuilder $urlBuilder
     * @param CurrencyHelper $nostoCurrencyHelper
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoCategoryBuilder $categoryBuilder,
        NostoStockHelper $stockHelper,
        NostoSkuCollection $skuCollection,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger,
        ManagerInterface $eventManager,
        ReviewFactory $reviewFactory,
        GalleryReadHandler $galleryReadHandler,
        NostoUrlBuilder $urlBuilder,
        CurrencyHelper $nostoCurrencyHelper
    ) {
        $this->nostoDataHelper = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoCategoryBuilder = $categoryBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoStockHelper = $stockHelper;
        $this->reviewFactory = $reviewFactory;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->urlBuilder = $urlBuilder;
        $this->skuCollection = $skuCollection;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return \Nosto\Object\Product\Product
     */
    public function build(Product $product, Store $store)
    {
        $nostoProduct = new \Nosto\Object\Product\Product();

        try {
            $nostoProduct->setUrl($this->urlBuilder->getUrlInStore($product, $store));
            $nostoProduct->setProductId((string)$product->getId());
            $nostoProduct->setName($product->getName());
            $nostoProduct->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalPriceInclTax(
                    $product
                ),
                $store
            );
            $nostoProduct->setPrice($price);
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductPriceInclTax(
                    $product
                ),
                $store
            );
            $nostoProduct->setListPrice($listPrice);
            $nostoProduct->setPriceCurrencyCode(
                $this->nostoCurrencyHelper->getTaggingCurrency(
                    $store
                )->getCode()
            );

            if ($this->nostoCurrencyHelper->getCurrencyCount($store) > 1) {
                $nostoProduct->setVariationId(
                    $this->nostoCurrencyHelper->getTaggingCurrency(
                        $store
                    )->getCode()
                );
            }

            $nostoProduct->setAvailability($this->buildAvailability($product));
            $nostoProduct->setCategories($this->nostoCategoryBuilder->buildCategories($product));
            $nostoProduct->setAlternateImageUrls($this->buildAlternativeImages($product));
            if ($this->nostoDataHelper->isInventoryTaggingEnabled($store)) {
                $nostoProduct->setInventoryLevel($this->nostoStockHelper->getQty($product));
            }
            if ($this->nostoDataHelper->isRatingTaggingEnabled($store)) {
                $nostoProduct->setRatingValue($this->buildRatingValue($product, $store));
                $nostoProduct->setReviewCount($this->buildReviewCount($product, $store));
            }
            if ($this->nostoDataHelper->isAltimgTaggingEnabled($store)) {
                $nostoProduct->setAlternateImageUrls($this->buildAlternativeImages($product));
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
            if ($product->hasData($marginAttribute)) {
                $nostoProduct->setSupplierCost($this->getAttributeValue($product, $marginAttribute));
            }
            $gtinAttribute = $this->nostoDataHelper->getGtinAttribute($store);
            if ($product->hasData($gtinAttribute)) {
                $nostoProduct->setGtin($this->getAttributeValue($product, $marginAttribute));
            }
            if (($tags = $this->buildTags($product)) !== []) {
                $nostoProduct->setTag1($tags);
            }

            //update customized tag1, Tag2 and Tag3
            $this->amendAttributeTags($product, $nostoProduct, $store);
        } catch (NostoException $e) {
            $this->logger->error($e->__toString());
        }
        $this->eventManager->dispatch(
            'nosto_product_load_after',
            ['product' => $nostoProduct, 'magentoProduct' => $product]
        );

        return $nostoProduct;
    }

    /**
     * Amends the product attributes to tags array if attributes are defined
     * and are present in product
     *
     * @param Product $product the magento product model.
     * @param \Nosto\Object\Product\Product $nostoProduct nosto product object
     * @param Store $store the store model.
     */
    protected function amendAttributeTags(Product $product, \Nosto\Object\Product\Product $nostoProduct, Store $store)
    {
        foreach (self::CUSTOMIZED_TAGS as $tag) {
            $getCustomizedAttribusMethodName = 'get' . $tag . 'Attributes';
            $addTagMethodName = 'add' . $tag;
            //getTag1Attributes(), getTag2Attributes() and getTag3Attributes() are called
            $attributesConfig = $this->nostoDataHelper->$getCustomizedAttribusMethodName($store);
            if ($attributesConfig == null) {
                continue;
            }
            $attributes = explode(',', $attributesConfig);

            foreach ($attributes as $productAttribute) {
                try {
                    $attributeValue = $this->getAttributeValue($product, $productAttribute);
                    if (empty($attributeValue)) {
                        continue;
                    }
                    //addTag1(), addTag2() and addTag3() are called
                    $nostoProduct->$addTagMethodName(sprintf('%s:%s', $productAttribute, $attributeValue));
                } catch (\Exception $e) {
                    $this->logger->error($e);
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
        if ($product->isAvailable()) {
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
        } else {
            return null;
        }
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
        } else {
            return null;
        }
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return string|null
     */
    public function buildImageUrl(Product $product, Store $store)
    {
        $primary = $this->nostoDataHelper->getProductImageVersion($store);
        $secondary = 'image'; // The "base" image.
        $media = $product->getMediaAttributeValues();
        $image = (isset($media[$primary])
            ? $media[$primary]
            : (isset($media[$secondary]) ? $media[$secondary] : null)
        );

        if (empty($image)) {
            return null;
        }

        return $product->getMediaConfig()->getMediaUrl($image);
    }

    /**
     * Adds the alternative image urls
     *
     * @param Product $product the product model.
     * @return array
     */
    public function buildAlternativeImages(Product $product)
    {
        $images = [];
        $this->galleryReadHandler->execute($product);
        foreach ($product->getMediaGalleryImages() as $image) {
            if (isset($image['url']) && (isset($image['disabled']) && $image['disabled'] !== '1')) {
                $images[] = $image['url'];
            }
        }

        return $images;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function buildTags(Product $product)
    {
        $tags = [];

        if (!$product->canConfigure()) {
            $tags[] = ProductInterface::ADD_TO_CART;
        }

        return $tags;
    }

    /**
     * Resolves "textual" product attribute value
     *
     * @param Product $product
     * @param $attribute
     * @return bool|float|int|null|string
     */
    private function getAttributeValue(Product $product, $attribute)
    {
        $value = null;
        try {
            $attributes = $product->getAttributes();
            if (isset($attributes[$attribute])) {
                $attributeObject = $attributes[$attribute];
                $frontend = $attributeObject->getFrontend();
                $frontendValue = $frontend->getValue($product);
                if (is_array($frontendValue)) {
                    $value = implode(",", $frontendValue);
                } elseif (is_scalar($frontendValue)) {
                    $value = $frontendValue;
                } elseif ($frontendValue instanceof Magento\Framework\Phrase) {
                    $value = (string)$frontendValue;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return $value;
    }
}
