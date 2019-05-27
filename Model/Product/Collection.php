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

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Sales\Api\Data\EntityInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\Product\ProductCollection;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Types\Product\ProductInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Collection
{
    private $productCollectionFactory;
    private $productVisibility;
    private $nostoProductBuilder;
    private $logger;

    /**
     * Collection constructor.
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductVisibility $productVisibility
     * @param Builder $nostoProductBuilder
     * @param NostoLogger $logger
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        ProductVisibility $productVisibility,
        NostoProductBuilder $nostoProductBuilder,
        NostoLogger $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->logger = $logger;
    }

    public function getCollection(Store $store)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->productCollectionFactory->create();
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter('status', ['eq' => '1']);
        $collection->addStoreFilter($store->getId());
        $collection->addAttributeToSelect('*');
        return $collection;
    }

    /**
     * @param Store $store
     * @param $id
     * @return ProductCollection
     * @throws NostoException
     */
    public function buildSingle(Store $store, $id)
    {
        $collection = $this->getCollection($store);
        $collection->addFieldToFilter(EntityInterface::ENTITY_ID, $id);
        return $this->build($store, $collection);
    }

    /**
     * @param Store $store
     * @param int $limit
     * @param int $offset
     * @return ProductCollection
     * @throws NostoException
     */
    public function buildMany(Store $store, $limit = 100, $offset = 0)
    {
        $collection = $this->getCollection($store);
        $currentPage = ($offset / $limit) + 1;
        $collection->getSelect()->limitPage($currentPage, $limit);
        $collection->setOrder(EntityInterface::CREATED_AT, $collection::SORT_ORDER_DESC);
        return $this->build($store, $collection);
    }

    /**
     * @param Store $store
     * @param $collection
     * @return ProductCollection
     * @throws NostoException
     */
    private function build(Store $store, $collection)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $products = new ProductCollection();
        $items = $collection->loadData();
        if ($items instanceof \Traversable === false && !is_array($items)) {
            throw new NostoException(
                sprintf('Invalid collection type %s for product export', get_class($collection))
            );
        }
        foreach ($items as $product) {
            /** @var \Magento\Catalog\Model\Product $product */
            try {
                $nostoProduct = $this->nostoProductBuilder->build(
                    $product,
                    $store,
                    NostoProductBuilder::NOSTO_SCOPE_API
                );
                if ($nostoProduct instanceof ProductInterface) {
                    $products->append($nostoProduct);
                }
            } catch (\Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $products;
    }
}
