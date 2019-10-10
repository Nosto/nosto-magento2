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

namespace Nosto\Tagging\Model\Product;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\ModelFilter;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Helper\Ratings as NostoRating;
use Nosto\Tagging\Helper\Variation as NostoVariationHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\CategoryString\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;
use Nosto\Tagging\Model\Product\Tags\LowStock as LowStockHelper;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Tagging\Model\Product\Variation\Collection as PriceVariationCollection;
use Nosto\Tagging\Model\Service\Stock\StockService;
use Nosto\Types\Product\ProductInterface;

class Builder
{
    use BuilderTrait {
        BuilderTrait::__construct as builderTraitConstruct; // @codingStandardsIgnoreLine
    }

    const CUSTOMIZED_TAGS = ['tag1', 'tag2', 'tag3'];
    private $nostoPriceHelper;
    private $nostoCategoryBuilder;
    private $nostoStockHelper;
    private $galleryReadHandler;
    private $eventManager;
    private $reviewFactory;
    private $urlBuilder;
    private $skuCollection;
    private $nostoCurrencyHelper;
    private $lowStockHelper;
    private $priceVariationCollection;
    private $nostoVariationHelper;
    private $categoryRepository;
    private $attributeSetRepository;
    private $nostoRatingHelper;

    /**
     * Builder constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoCategoryBuilder $categoryBuilder
     * @param StockService $stockService
     * @param NostoSkuCollection $skuCollection
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param GalleryReadHandler $galleryReadHandler
     * @param NostoUrlBuilder $urlBuilder
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param LowStockHelper $lowStockHelper
     * @param PriceVariationCollection $priceVariationCollection
     * @param NostoVariationHelper $nostoVariationHelper
     * @param NostoRating $nostoRatingHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoCategoryBuilder $categoryBuilder,
        StockService $stockService,
        NostoSkuCollection $skuCollection,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        GalleryReadHandler $galleryReadHandler,
        NostoUrlBuilder $urlBuilder,
        CurrencyHelper $nostoCurrencyHelper,
        LowStockHelper $lowStockHelper,
        PriceVariationCollection $priceVariationCollection,
        NostoVariationHelper $nostoVariationHelper,
        NostoRating $nostoRatingHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoCategoryBuilder = $categoryBuilder;
        $this->eventManager = $eventManager;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->urlBuilder = $urlBuilder;
        $this->skuCollection = $skuCollection;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->lowStockHelper = $lowStockHelper;
        $this->builderTraitConstruct(
            $nostoHelperData,
            $stockService,
            $logger
        );
        $this->priceVariationCollection = $priceVariationCollection;
        $this->nostoVariationHelper = $nostoVariationHelper;
        $this->nostoRatingHelper = $nostoRatingHelper;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return NostoProduct|null
     * @throws Exception
     */
    public function build(
        Product $product,
        Store $store
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

            if ($this->nostoCurrencyHelper->exchangeRatesInUse($store)) {
                $nostoProduct->setVariationId(
                    $this->nostoCurrencyHelper->getTaggingCurrency(
                        $store
                    )->getCode()
                );
            } elseif ($this->getDataHelper()->isPricingVariationEnabled($store)) {
                $nostoProduct->setVariationId(
                    $this->nostoVariationHelper->getDefaultVariationCode()
                );
            }

            $nostoProduct->setAvailability($this->buildAvailability($product, $store));
            $nostoProduct->setCategories($this->nostoCategoryBuilder->buildCategories($product, $store));
            if ($this->getDataHelper()->isInventoryTaggingEnabled($store)) {
                $inventoryLevel = $this->getStockService()->getQuantity($product);
                $nostoProduct->setInventoryLevel($inventoryLevel);
            }
            $rating = $this->nostoRatingHelper->getRatings($product, $store);
            if ($rating !== null) {
                $nostoProduct->setRatingValue($rating->getRating());
                $nostoProduct->setReviewCount($rating->getReviewCount());
            }
            if ($this->getDataHelper()->isAltimgTaggingEnabled($store)) {
                $nostoProduct->setAlternateImageUrls($this->buildAlternativeImages($product, $store));
            }
            if ($this->getDataHelper()->isVariationTaggingEnabled($store)) {
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
            $brandAttribute = $this->getDataHelper()->getBrandAttribute($store);
            if ($product->hasData($brandAttribute)) {
                $nostoProduct->setBrand($this->getAttributeValue($product, $brandAttribute));
            }
            $marginAttribute = $this->getDataHelper()->getMarginAttribute($store);
            if ($product->hasData($marginAttribute)) {
                $nostoProduct->setSupplierCost($this->getAttributeValue($product, $marginAttribute));
            }
            $gtinAttribute = $this->getDataHelper()->getGtinAttribute($store);
            if ($product->hasData($gtinAttribute)) {
                $nostoProduct->setGtin($this->getAttributeValue($product, $gtinAttribute));
            }
            if (($tags = $this->buildTags($product, $store)) !== []) {
                $nostoProduct->setTag1($tags);
            }

            $nostoProduct->setCustomFields($this->getCustomFieldsWithAttributes($product, $store));

            // Update customised Tag1, Tag2 and Tag3
            $this->amendAttributeTags($product, $nostoProduct, $store);

            // When using customer group price variations, set the variations
            if ($this->getDataHelper()->isPricingVariationEnabled($store)
                && $this->getDataHelper()->isMultiCurrencyDisabled($store)
            ) {
                $nostoProduct->setVariations(
                    $this->priceVariationCollection->build($product, $nostoProduct, $store)
                );
            }
            if ($this->getDataHelper()->isTagDatePublishedEnabled($store)) {
                $nostoProduct->setDatePublished($product->getCreatedAt());
            }

            // These will be always fetched from price index tables
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
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
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
     * Adds selected attributes to all tags also in the custom fields section
     *
     * @param Product $product
     * @param Store $store
     * @return array
     */
    private function getCustomFieldsWithAttributes(Product $product, Store $store)
    {
        $customFields = $this->buildCustomFields($product, $store);
        $attributes = $this->getAttributesFromAllTags($store);
        if (!$attributes) {
            return $customFields;
        }
        foreach ($product->getAttributes() as $key => $productAttribute) {
            if (in_array($key, $attributes, false)) {
                $attributeValue = $this->getAttributeValue($product, $key);
                if ($attributeValue === null || $attributeValue === '') {
                    continue;
                }
                $customFields[$key] = $attributeValue;
            }
        }
        return $customFields;
    }

    /**
     * Returns unique selected attributes from all tags
     *
     * @param Store $store
     * @return array
     */
    private function getAttributesFromAllTags(Store $store)
    {
        $attributes = [];
        foreach (self::CUSTOMIZED_TAGS as $tag) {
            $tagAttributes = $this->getDataHelper()->getTagAttributes($tag, $store);
            if (!$tagAttributes) {
                continue;
            }
            foreach ($tagAttributes as $productAttribute) {
                $attributes[] = $productAttribute;
            }
        }
        if ($attributes) {
            return array_unique($attributes);
        }
        return [];
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
            $attributes = $this->getDataHelper()->getTagAttributes($tag, $store);
            if (!$attributes) {
                continue;
            }
            foreach ($attributes as $productAttribute) {
                try {
                    $attributeValue = $this->getAttributeValue($product, $productAttribute);
                    if (empty($attributeValue)) {
                        continue;
                    }

                    switch ($tag) {
                        case 'tag1':
                            $nostoProduct->addTag1(sprintf('%s:%s', $productAttribute, $attributeValue));
                            break;
                        case 'tag2':
                            $nostoProduct->addTag2(sprintf('%s:%s', $productAttribute, $attributeValue));
                            break;
                        case 'tag3':
                            $nostoProduct->addTag3(sprintf('%s:%s', $productAttribute, $attributeValue));
                            break;
                        default:
                            throw new NostoException('Method add' . $tag . ' is not defined.');
                    }
                } catch (Exception $e) {
                    $this->getLogger()->exception($e);
                }
            }
        }
    }

    /**
     * Generates the availability for the product
     *
     * @param Product $product
     * @param Store $store
     * @return string
     */
    private function buildAvailability(Product $product, Store $store)
    {
        $availability = ProductInterface::OUT_OF_STOCK;
        $isInStock = $this->isInStock($product, $store);
        if (!$product->isVisibleInSiteVisibility()
           || (!$this->isAvailableInStore($product, $store) && $isInStock)
        ) {
            $availability = ProductInterface::INVISIBLE;
        } elseif ($isInStock
            && $product->isAvailable()
        ) {
            $availability = ProductInterface::IN_STOCK;
        }

        return $availability;
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

        if ($this->getDataHelper()->isLowStockIndicationEnabled($store)
            && $this->lowStockHelper->build($product)
        ) {
            $tags[] = ProductInterface::LOW_STOCK;
        }

        return $tags;
    }
}
