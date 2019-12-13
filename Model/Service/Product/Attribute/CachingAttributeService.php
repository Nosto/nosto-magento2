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
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class CachingAttributeService extends AbstractAttributeService
{
    /** @var array */
    private $productAttributeCache = [];

    /** @var AttributeServiceInterface */
    private $attributeService;

    /** @var int */
    private $maxCachedProducts;

    /**
     * CachingAttributeService constructor.
     * @param AttributeServiceInterface $attributeService
     * @param $maxCachedProducts
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoLogger $logger,
        AttributeServiceInterface $attributeService,
        AttributeProviderInterface $attributeProvider,
        $maxCachedProducts
    ) {
        parent::__construct($nostoHelperData, $logger, $attributeProvider);
        $this->attributeService = $attributeService;
        $this->maxCachedProducts = $maxCachedProducts;
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
        if (!isset($this->productAttributeCache[$storeId])) {
            $this->productAttributeCache[$storeId] = [];
        }
        if (!isset($this->productAttributeCache[$storeId][$productId])) {
            $this->productAttributeCache[$storeId][$productId] = [];
        }
        $this->productAttributeCache[$storeId][$productId][$attributeCode] = $value;
        $this->sliceCache($store);
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
     * Returns if the value has been cached
     *
     * @param Product $product
     * @param StoreInterface $store
     * @param $attributeCode
     * @return bool
     */
    private function isAttributeCached(Product $product, StoreInterface $store, $attributeCode): bool
    {
        $storeId = $store->getId();
        $productId = $product->getId();
        return isset($this->productAttributeCache[$storeId][$productId][$attributeCode]);
    }

    /**
     * Keeps the cache sizes within the given limits
     *
     * @param StoreInterface $store
     */
    private function sliceCache(StoreInterface $store)
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
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValueByAttributeCode(Product $product, $attributeCode)
    {
        if ($this->isAttributeCached($product, $product->getStore(), $attributeCode) === false) {
            $value = $this->attributeService->getAttributeValueByAttributeCode($product, $attributeCode);
            $this->saveAttributeToCache($product, $product->getStore(), $attributeCode, $value);
        }
        return $this->getAttributeFromCacheByAttributeCode($product, $product->getStore(), $attributeCode);
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValue(Product $product, AbstractAttribute $attribute)
    {
        return $this->getAttributeValueByAttributeCode($product, $attribute->getAttributeCode());
    }
}
