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

use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Repository wrapper class for fetching products
 *
 * @package Nosto\Tagging\Model\Product
 */
class Repository
{
    private $parentProductIdCache = [];

    private $productRepository;
    private $searchCriteriaBuilder;
    private $configurableProduct;
    private $filterGroupBuilder;
    private $filterBuilder;
    private $configurableType;
    private $productVisibility;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param ProductRepository\Proxy $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigurableProduct $configurableProduct
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param ConfigurableType $configurableType
     * @param ProductVisibility $productVisibility
     */
    public function __construct(
        ProductRepository\Proxy $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ConfigurableProduct $configurableProduct,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        ConfigurableType $configurableType,
        ProductVisibility $productVisibility
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->configurableProduct = $configurableProduct;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->configurableType = $configurableType;
        $this->productVisibility = $productVisibility;
    }

    /**
     * Gets products by product ids
     *
     * @param array $ids
     * @return ProductSearchResultsInterface
     */
    public function getByIds(array $ids)
    {
        $this->productRepository->cleanCache();
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();
        return $this->productRepository->getList($searchCriteria);
    }

    /**
     * Gets products that have scheduled pricing active
     *
     * @return ProductSearchResultsInterface
     * @suppress PhanTypeMismatchArgument
     * @throws \Exception
     */
    public function getWithActivePricingSchedule()
    {
        $today = new \DateTime('now'); // @codingStandardsIgnoreLine
        $filterEndDateGreater = $this->filterBuilder
            ->setField('special_to_date')
            ->setValue($today->format('Y-m-d ' . '00:00:00'))
            ->setConditionType('gt')
            ->create();
        $filterEndDateNotSet = $this->filterBuilder
            ->setField('special_to_date')
            ->setValue(['null' => true])
            ->setConditionType('eq')
            ->create();

        $filterGroup = $this->filterGroupBuilder->setFilters([$filterEndDateGreater, $filterEndDateNotSet])->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$filterGroup])
            ->addFilter('special_from_date', $today->format('Y-m-d') . ' 00:00:00', 'gte')
            ->create();
        return $this->productRepository->getList($searchCriteria);
    }

    /**
     * Gets a product that is active in a given Store
     *
     * @return Product|null
     * @suppress PhanTypeMismatchArgument
     *
     */
    public function getRandomSingleActiveProduct()
    {
        $filterStatus = $this->filterBuilder
            ->setField('status')
            ->setValue(1)
            ->setConditionType('eq')
            ->create();

        $filterVisible = $this->filterBuilder
            ->setField('visibility')
            ->setValue($this->productVisibility->getVisibleInSiteIds())
            ->setConditionType('in')
            ->create();

        $filterGroup = $this->filterGroupBuilder->setFilters([$filterStatus, $filterVisible])->create();
        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$filterGroup])
            ->setCurrentPage(1)
            ->setPageSize(1)
            ->create();

        $product = $this->productRepository->getList($searchCriteria)->setTotalCount(1);

        foreach ($product->getItems() as $item) {
            /** @var \Magento\Catalog\Model\Product $item */
            return $item;
        }
        return null;
    }

    /**
     * Gets the parent products for simple product
     *
     * @param Product $product
     * @return string[]|null
     * @suppress PhanTypeMismatchReturn
     */
    public function resolveParentProductIds(Product $product)
    {
        if ($this->getParentIdsFromCache($product)) {
            return $this->getParentIdsFromCache($product);
        }
        $parentProductIds = null;
        if ($product->getTypeId() === Type::TYPE_SIMPLE) {
            $parentProductIds = $this->configurableProduct->getParentIdsByChild(
                $product->getId()
            );
            $this->saveParentIdsToCache($product, $parentProductIds);
        }

        return $parentProductIds;
    }

    /**
     * Gets the parent products for simple product using product ID
     *
     * @param $productId
     * @param $typeId
     * @return string[]|null
     * @suppress PhanTypeMismatchReturn
     */
    public function resolveParentProductIdsByProductId($productId, $typeId)
    {
        $cachedProduct = $this->getParentIdsFromCacheByProductId($productId);
        if ($cachedProduct) {
            return $cachedProduct;
        }
        if ($typeId === Type::TYPE_SIMPLE) {
            $parentProductIds = $this->configurableProduct->getParentIdsByChild(
                $productId
            );
            $this->saveParentIdsToCacheByProductId($productId, $parentProductIds);
            return $parentProductIds;
        }
        return null;
    }

    /**
     * Gets the variations / SKUs of configurable product
     *
     * @param Product $product
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSkus(Product $product)
    {
        $skuIds = $this->configurableType->getChildrenIds($product->getId());
        $products = [];
        foreach ($skuIds as $batch => $skus) {
            if (is_array($skus)) {
                foreach ($skus as $skuId) {
                    // We need to load these one by one in order to get correct stock / availability info
                    $products[] = $this->productRepository->getById($skuId);
                }
            }
        }

        return $products;
    }

    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param Product $product
     * @return string[]|null
     */
    private function getParentIdsFromCache(Product $product)
    {
        if (isset($this->parentProductIdCache[$product->getId()])) {
            return $this->parentProductIdCache[$product->getId()];
        }

        return null;
    }

    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param $productId
     * @return string[]|null
     */
    private function getParentIdsFromCacheByProductId($productId)
    {
        return $this->parentProductIdCache[$productId] ?? null;
    }

    /**
     * Saves the parents product ids to internal cache to avoid redundant
     * database queries
     *
     * @param Product $product
     * @param string[] $parentProductIds
     */
    private function saveParentIdsToCache(Product $product, $parentProductIds)
    {
        $this->parentProductIdCache[$product->getId()] = $parentProductIds;
    }

    /**
     * Saves the parents product ids to internal cache to avoid redundant
     * database queries
     *
     * @param $productId
     * @param string[] $parentProductIds
     */
    private function saveParentIdsToCacheByProductId($productId, $parentProductIds)
    {
        $this->parentProductIdCache[$productId] = $parentProductIds;
    }
}
