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
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Event\ManagerInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Exception\NostoException;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Types\Product\ProductInterface;
use Psr\Log\LoggerInterface;

class Builder
{
    private $nostoDataHelper;
    private $nostoPriceHelper;
    private $nostoCategoryBuilder;
    private $categoryRepository;
    private $eventManager;
    private $logger;
    private $reviewFactory;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoCategoryBuilder $categoryBuilder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     * @param ReviewFactory $reviewFactory
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoCategoryBuilder $categoryBuilder,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger,
        ManagerInterface $eventManager,
        ReviewFactory $reviewFactory
    ) {
        $this->nostoDataHelper = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoCategoryBuilder = $categoryBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->reviewFactory = $reviewFactory;
    }

    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return \Nosto\Object\Product\Product
     */
    public function build(Product $product, StoreInterface $store)
    {
        $nostoProduct = new \Nosto\Object\Product\Product();

        try {
            $nostoProduct->setUrl($this->buildUrl($product, $store));
            $nostoProduct->setProductId((string)$product->getId());
            $nostoProduct->setName($product->getName());
            $nostoProduct->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->nostoPriceHelper->getProductFinalPriceInclTax($product);
            $nostoProduct->setPrice($price);
            $listPrice = $this->nostoPriceHelper->getProductPriceInclTax($product);
            $nostoProduct->setListPrice($listPrice);
            /** @noinspection PhpUndefinedMethodInspection */
            $nostoProduct->setPriceCurrencyCode($store->getBaseCurrencyCode());
            $nostoProduct->setAvailable($product->isAvailable());
            $nostoProduct->setCategories($this->nostoCategoryBuilder->buildCategories($product));
            $nostoProduct->setRatingValue($this->buildRatingValue($product, $store));
            $nostoProduct->setReviewCount($this->buildReviewCount($product, $store));

            // Optional properties.

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

            if ($product->hasData('manufacturer')) {
                $nostoProduct->setBrand(
                    $product->getAttributeText('manufacturer')
                );
            }
            if (($tags = $this->buildTags($product)) !== []) {
                $nostoProduct->setTag1($tags);
            }
        } catch (NostoException $e) {
            $this->logger->error($e->__toString());
        }

        $this->eventManager->dispatch('nosto_product_load_after', ['product' => $nostoProduct]);

        return $nostoProduct;
    }

    /**
     * Helper method to fetch and return the normalised rating value for a product. The rating is
     * normalised to a 0-5 value.
     *
     * @param Product $product the product whose rating value to fetch
     * @param StoreInterface $store the store scope in which to fetch the rating
     * @return float the normalized rating value of the product
     */
    private function buildRatingValue(Product $product, StoreInterface $store) {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return round($product->getRatingSummary()->getRatingSummary() / 20, 1);
    }

    /**
     * Helper method to fetch and return the total review count for a product. The review counts are
     * returned as is.
     *
     * @param Product $product the product whose rating value to fetch
     * @param StoreInterface $store the store scope in which to fetch the rating
     * @return float the normalized rating value of the product
     */
    private function buildReviewCount(Product $product, StoreInterface $store) {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return $product->getRatingSummary()->getReviewsCount();
    }

    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return string
     */
    public function buildUrl(Product $product, StoreInterface $store)
    {
        return $product->getUrlInStore(
            [
                '_ignore_category' => true,
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            ]
        );
    }

    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return string|null
     */
    public function buildImageUrl(Product $product, StoreInterface $store)
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
     * @param Product $product
     * @return array
     */
    public function buildTags(Product $product)
    {
        $tags = [];
        /** @var Attribute $attr */
        foreach ($product->getAttributes() as $attr) {
            if ($attr->getIsVisibleOnFront()
                && $product->hasData($attr->getAttributeCode())
            ) {
                $label = $attr->getStoreLabel();
                $value = $attr->getFrontend()->getValue($product);
                if (is_string($label) && $label !== "" && is_string($value) && $value !== "") {
                    $tags[] = "{$label}: {$value}";
                }
            }
        }

        if (!$product->canConfigure()) {
            $tags[] = ProductInterface::ADD_TO_CART;
        }

        return $tags;
    }
}
