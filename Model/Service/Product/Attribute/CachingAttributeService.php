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

namespace Nosto\Tagging\Model\Service\Product\Attribute;

use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;

class CachingAttributeService implements AttributeServiceInterface
{
    /** @var array */
    private $productAttributeCache = [];

    /** @var AttributeServiceInterface */
    private $singleAttributeCache = [];

    /** @var AttributeServiceInterface */
    private $attributeService;

    /** @var int */
    private $maxCachedProducts;

    /**
     * DefaultAttributeService constructor.
     * @param NostoHelperData $nostoHelperData
     * @param Logger $logger
     */
    public function __construct(
        AttributeServiceInterface $attributeService,
        $maxCachedProducts
    ) {
        $this->attributeService = $attributeService;
        $this->maxCachedProducts = $maxCachedProducts;
    }

    /**
     * Returns all cached attributes and values for a product.
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array|null
     */
    private function getProductAttributesFromCache(Product $product, StoreInterface $store)
    {
        $storeId = $store->getId();
        $productId = $product->getId();
        return $this->productAttributeCache[$storeId][$productId] ?? null;
    }

    /**
     * Saves products attributes & values to the cache.
     *
     * @param Product $product
     * @param StoreInterface $store
     * @param array $attributes
     */
    private function saveProductAttributesToCache(Product $product, StoreInterface $store, array $attributes)
    {
        $storeId = $store->getId();
        $productId = $product->getId();
        if (!isset($this->productAttributeCache[$storeId])) {
            $this->productAttributeCache[$storeId] = [];
        }
        $this->productAttributeCache[$storeId][$productId] = $attributes;
        foreach ($attributes as $key => $value) {
            $this->saveAttributeToCache($product, $store, $key, $value);
        }
        $this->sliceCaches($store);
    }

    /**
     * Saves a single attribute and value into the cache.
     *
     * @param Product $product
     * @param StoreInterface $store
     * @param $attributeCode
     * @param $value
     */
    private function saveAttributeToCache(Product $product, StoreInterface $store, $attributeCode, $value)
    {
        $storeId = $store->getId();
        $productId = $product->getId();
        if (!isset($this->singleAttributeCache[$storeId])) {
            $this->singleAttributeCache[$storeId] = [];
        }
        if (!isset($this->singleAttributeCache[$storeId][$productId])) {
            $this->singleAttributeCache[$storeId][$productId] = [];
        }
        $this->singleAttributeCache[$storeId][$productId][$attributeCode] = $value;
        $this->sliceCaches($store);
    }

    /**
     * Returns the value of given product, store and attribute code from the cache
     * if it exists.
     *
     * @param Product $product
     * @param StoreInterface $store
     * @param $attributeCode
     * @return |null
     */
    private function getAttributeFromCacheByAttributeCode(Product $product, StoreInterface $store, $attributeCode)
    {
        $storeId = $store->getId();
        $productId = $product->getId();
        return $this->productAttributeCache[$storeId][$productId][$attributeCode] ?? null;
    }


    /**
     * Keeps the cache sizes within the given limits
     *
     * @param StoreInterface $store
     */
    private function sliceCaches(StoreInterface $store)
    {
        $storeId = $store->getId();
        /* Product attribute cache slicing */
        $productCacheOffset = count($this->productAttributeCache[$storeId])-$this->maxCachedProducts;
        if ($productCacheOffset > 0) {
            $this->productAttributeCache[$storeId] = array_slice(
                $this->productAttributeCache[$storeId],
                $productCacheOffset,
                $this->maxCachedProducts,
                true
            );
        }
        /* Single attribute cache slicing */
        $attributeCacheOffset = count($this->singleAttributeCache[$storeId])-$this->maxCachedProducts;
        if ($attributeCacheOffset > 0) {
            $this->singleAttributeCache[$storeId] = array_slice(
                $this->singleAttributeCache[$storeId],
                $attributeCacheOffset,
                $this->maxCachedProducts,
                true
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(Product $product, StoreInterface $store): array
    {
        $attributes = $this->getProductAttributesFromCache($product, $store);
        if ($attributes === null) {
            $attributes = $this->attributeService->getAttributes($product, $store);
            $this->saveProductAttributesToCache($product, $store, $attributes);
        }
        return $attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValueByAttributeCode(Product $product, $attributeCode)
    {
        $value = $this->getAttributeFromCacheByAttributeCode($product, $product->getStore(), $attributeCode);
        if ($value === null) {
            $value = $this->attributeService->getAttributeValueByAttributeCode($product, $attributeCode);
        }
        $this->saveAttributeToCache($product, $product->getStore(), $attributeCode, $value);
        return $this->attributeService->getAttributeValueByAttributeCode($product, $attributeCode);
    }
}
