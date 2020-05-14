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

namespace Nosto\Tagging\Model\Service\Cache;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Object\Product\Product;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Cache\Type\ProductDataInterface;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Product\ProductSerializerInterface;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class CacheService extends AbstractService
{
    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /** @var ProductSerializerInterface */
    private $productSerializer;

    /** @var ProductDataInterface */
    private $productDataCache;

    /**
     * CacheService constructor.
     * @param NostoLogger $logger
     * @param TimezoneInterface $magentoTimeZone
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoAccountHelper $nostoAccountHelper
     * @param ProductSerializerInterface $productSerializer
     * @param ProductDataInterface $productDataCache
     */
    public function __construct(
        NostoLogger $logger,
        TimezoneInterface $magentoTimeZone,
        NostoDataHelper $nostoDataHelper,
        NostoAccountHelper $nostoAccountHelper,
        ProductSerializerInterface $productSerializer,
        ProductDataInterface $productDataCache
    ) {
        parent::__construct($nostoDataHelper, $nostoAccountHelper, $logger);
        $this->magentoTimeZone = $magentoTimeZone;
        $this->productSerializer = $productSerializer;
        $this->productDataCache = $productDataCache;
    }

    /**
     * @param NostoProductInterface $nostoProduct
     * @param StoreInterface $store
     */
    public function upsert(NostoProductInterface $nostoProduct, StoreInterface $store)
    {
        try {
            $serializedNostoProduct = $this->productSerializer->toString($nostoProduct);
            $this->productDataCache->save(
                $serializedNostoProduct,
                $this->generateCacheKey($nostoProduct->getProductId(), $store->getId())
            );
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return Product|null
     */
    public function get(ProductInterface $product, StoreInterface $store)
    {
        return $this->getById($product->getId(), $store->getId());
    }

    /**
     * @param StoreInterface $store
     * @param array $productIds
     */
    public function removeByProductIds(StoreInterface $store, array $productIds)
    {
        $storeId = $store->getId();
        foreach ($productIds as $productId) {
            $this->productDataCache->remove(
                $this->generateCacheKey($productId, $storeId)
            );
        }
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @return Product|null
     */
    private function getById($productId, $storeId)
    {
        $cachedProduct = $this->productDataCache->load($this->generateCacheKey($productId, $storeId));
        return $cachedProduct ? $this->productSerializer->fromString($cachedProduct) : null;
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    private function generateCacheKey($productId, $storeId)
    {
        return sprintf(
            '%s-%d-%d',
            $this->productDataCache->getTag(),
            $productId,
            $storeId
        );
    }
}
