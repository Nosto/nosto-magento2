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
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\SearchResultInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data;
use Magento\Catalog\Model\Product\Type;

/**
 * Repository wrapper class for fetching products
 *
 * @package Nosto\Tagging\Model\Product
 */
class Repository
{
    const FIELD_UPDATED_AT = 'updated_at';
    const FIELD_NEWS_FROM = 'news_from_date';
    const FIELD_NEWS_TO = 'news_to_date';
    const FIELD_SPECIAL_FROM_DATE = 'special_from_date';
    const FIELD_SPECIAL_TO_DATE = 'special_to_date';

    private $cache = array();

    private $nostoDataHelper;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $configurableProduct;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param ProductRepository\Proxy $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Data $nostoDataHelper
     * @param ConfigurableProduct $configurableProduct
     */
    public function __construct(
        ProductRepository\Proxy $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Data $nostoDataHelper,
        ConfigurableProduct $configurableProduct
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->configurableProduct = $configurableProduct;
    }

    /**
     * Gets the products that updated within the given time interval
     * @param \DateInterval $interval
     * @return SearchResultInterface
     */
    public function getUpdatedWithinInterval(\DateInterval $interval)
    {
        $date = new \DateTime('now');
        $previousDate = new \DateTime('now');
        $previousDate->sub($interval);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(self::FIELD_UPDATED_AT, $date->format('Y-m-d H:i:s'), 'lt')
            ->setPageSize(3)
            ->setCurrentPage(1)
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets the products by ids
     * has ended recently
     * @param array $ids
     * @return SearchResultInterface
     */
    public function getByIds(array $ids)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets the products that have been scheduled for changes or the scheduling
     * has ended recently
     * @param \DateInterval $interval
     * @return SearchResultInterface
     */
    public function getScheduledProducts(\DateInterval $interval)
    {
        $date = new \DateTime('now');
        $previousDate = new \DateTime('now');
        $previousDate->sub($interval);

        // start schedule 1 hr < now & end schedule > now OR
        // end schedule 1 hr < now
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(self::FIELD_UPDATED_AT, $date->format('Y-m-d H:i:s'), 'lt')
            ->addFilter(self::FIELD_UPDATED_AT, $previousDate->format('Y-m-d H:i:s'), 'gt')
            ->setPageSize(150)
            ->setCurrentPage(1)
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets the parent products for simple product
     * @return Product[]
     */
    public function resolveParentProducts(Product $product)
    {
        if ($this->getParentsFromCache($product)) {

            return $this->getParentsFromCache($product);
        }
        $parentProducts = null;
        if ($product->getTypeId() === Type::TYPE_SIMPLE) {
            $parentIds = $this->configurableProduct->getParentIdsByChild($product->getId());
            if (count($parentIds) > 0) {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('entity_id', $parentIds, 'in')
                    ->create();
                $parentProducts = $this->productRepository->getList($searchCriteria)->getItems();
            }
        }
        $this->saveParentsToCache($product, $parentProducts);

        return $parentProducts;
    }

    /**
     * Returns the variations from
     *
     * @param Product $product
     * @return mixed|null
     */
    private function getParentsFromCache(Product $product)
    {
        if (isset($this->cache[$product->getId()])) {

            return $this->cache[$product->getId()];
        }

        return null;
    }

    /**
     * Saves the parents products to internal cache to avoid redundant database queries
     *
     * @param Product $product
     * @param $parentProducts
     */
    private function saveParentsToCache(Product $product, $parentProducts)
    {
        $this->cache[$product->getId()] = $parentProducts;
    }
}
