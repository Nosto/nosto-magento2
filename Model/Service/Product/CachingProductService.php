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

namespace Nosto\Tagging\Model\Service\Product;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\Service\Cache\CacheService;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class CachingProductService implements ProductServiceInterface
{

    /** @var CacheRepository */
    private $nostoCacheRepository;

    /** @var Logger */
    private $nostoLogger;

    /** @var CacheService */
    private $nostoCacheService;

    /** @var ProductSerializerInterface */
    private $productSerializer;

    /**
     * Index constructor.
     * @param CacheRepository $nostoCacheRepository
     * @param Logger $nostoLogger
     * @param CacheService $nostoCacheService
     * @param ProductSerializerInterface $productSerializer
     */
    public function __construct(
        CacheRepository $nostoCacheRepository,
        Logger $nostoLogger,
        CacheService $nostoCacheService,
        ProductSerializerInterface $productSerializer
    ) {
        $this->nostoCacheRepository = $nostoCacheRepository;
        $this->nostoLogger = $nostoLogger;
        $this->nostoCacheService = $nostoCacheService;
        $this->productSerializer = $productSerializer;
    }

    /**
     * Get Nosto Product
     * If is not indexed or dirty, rebuilds, saves product to the indexed table
     * and returns NostoProduct from indexed product
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return NostoProductInterface|null
     */
    public function getProduct(ProductInterface $product, StoreInterface $store)
    {
        try {
            $cachedProduct = $this->nostoCacheRepository->getOneByProductAndStore($product, $store);
            //In case the product is not present in the index table
            if ($cachedProduct === null) {
                $this->nostoCacheService->updateOrCreateDirtyEntity($product, $store);
                $cachedProduct = $this->nostoCacheRepository->getOneByProductAndStore($product, $store);
            }
            //If it is dirty rebuild the product data
            if ($cachedProduct->getIsDirty()) {
                $cachedProduct = $this->nostoCacheService->rebuildDirtyProduct($cachedProduct);
            }
            return $this->productSerializer->fromString(
                $cachedProduct->getProductData()
            );
        } catch (Exception $e) {
            $this->nostoLogger->exception($e);
            return null;
        }
    }
}
