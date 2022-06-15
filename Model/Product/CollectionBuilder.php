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

namespace Nosto\Tagging\Model\Product;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Model\Product\ProductCollection as NostoProductCollection;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder as ProductCollectionBuilder;
use Nosto\Tagging\Model\Service\Product\ProductServiceInterface;
use Traversable;

/**
 * A builder class for building collection containing Nosto products
 */
class CollectionBuilder
{
    /** @var ProductCollectionBuilder */
    private ProductCollectionBuilder $productCollectionBuilder;

    /** @var ProductServiceInterface */
    private ProductServiceInterface $productService;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * Collection constructor.
     * @param ProductCollectionBuilder $productCollectionBuilder
     * @param ProductServiceInterface $productService
     * @param NostoLogger $logger
     */
    public function __construct(
        ProductCollectionBuilder $productCollectionBuilder,
        ProductServiceInterface $productService,
        NostoLogger $logger
    ) {
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->productService = $productService;
        $this->logger = $logger;
    }

    /**
     * @param Store $store
     * @param $id
     * @return NostoProductCollection
     * @throws NostoException
     */
    public function buildSingle(Store $store, $id)
    {
        return $this->load(
            $store,
            $this->productCollectionBuilder->buildSingle($store, $id)
        );
    }

    /**
     * @param Store $store
     * @param int $limit
     * @param int $offset
     * @return NostoProductCollection
     * @throws NostoException
     */
    public function buildMany(Store $store, int $limit = 100, int $offset = 0)
    {
        return $this->load(
            $store,
            $this->productCollectionBuilder->buildMany($store, $limit, $offset)
        );
    }

    /**
     * @param Store $store
     * @param $collection
     * @return NostoProductCollection
     * @throws NostoException
     */
    private function load(Store $store, $collection)
    {
        /** @var ProductCollection $collection */
        $products = new NostoProductCollection();
        $items = $collection->load();
        if ($items instanceof Traversable === false && !is_array($items)) {
            throw new NostoException(
                sprintf('Invalid collection type %s for product export', get_class($collection))
            );
        }
        foreach ($items as $product) {
            /** @var Product $product */
            try {
                $nostoProduct = $this->productService->getProduct(
                    $product->getId(),
                    $store
                );
                if ($nostoProduct !== null) {
                    $products->append($nostoProduct);
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $products;
    }
}
