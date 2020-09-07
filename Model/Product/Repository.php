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

use Magento\Catalog\Api\Data\ProductInterface;
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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\ResourceModel\Sku;
use Nosto\Tagging\Model\Service\Stock\Provider\StockProviderInterface;

/**
 * Repository wrapper class for fetching products
 */
class Repository
{
    const MAX_SKUS = 5000;

    private $parentProductIdCache = [];

    private $productRepository;
    private $searchCriteriaBuilder;
    private $configurableProduct;
    private $filterGroupBuilder;
    private $filterBuilder;
    private $configurableType;
    private $productVisibility;
    private $stockProvider;
    private $skuResource;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigurableProduct $configurableProduct
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param ConfigurableType $configurableType
     * @param ProductVisibility $productVisibility
     * @param StockProviderInterface $stockProvider
     * @param Sku $skuResource
     */
    public function __construct(
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ConfigurableProduct $configurableProduct,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        ConfigurableType $configurableType,
        ProductVisibility $productVisibility,
        StockProviderInterface $stockProvider,
        Sku $skuResource
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->configurableProduct = $configurableProduct;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->configurableType = $configurableType;
        $this->productVisibility = $productVisibility;
        $this->stockProvider = $stockProvider;
        $this->skuResource = $skuResource;
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
            /** @var Product $item */
            return $item;
        }
        return null;
    }

    /**
     * Gets the parent products for simple product
     *
     * @param ProductInterface $product
     * @return string[]|null
     * @suppress PhanTypeMismatchReturn
     */
    public function resolveParentProductIds(ProductInterface $product)
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
     * Gets the variations / SKUs of configurable product
     *
     * @param Product $product
     * @return array
     */
    public function getSkus(Product $product)
    {
        $skuIds = $this->getSkuIds($product);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $skuIds, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->setTotalCount(self::MAX_SKUS);

        return $products->getItems();
    }

    /**
     * Returns the sku ids for a specific product
     *
     * @param Product $product
     * @return array
     */
    public function getSkuIds(Product $product)
    {
        $batched = $this->configurableType->getChildrenIds($product->getId());
        $flat = [];
        foreach ($batched as $batch => $ids) {
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $flat[$id] = $id;
                }
            }
        }

        return $flat;
    }

    /**
     * Get parent ids from cache. Return null if the cache is not available
     *
     * @param ProductInterface $product
     * @return string[]|null
     */
    private function getParentIdsFromCache(ProductInterface $product)
    {
        if (isset($this->parentProductIdCache[$product->getId()])) {
            return $this->parentProductIdCache[$product->getId()];
        }

        return null;
    }

    /**
     * Saves the parents product ids to internal cache to avoid redundant
     * database queries
     *
     * @param ProductInterface $product
     * @param string[] $parentProductIds
     */
    private function saveParentIdsToCache(ProductInterface $product, $parentProductIds)
    {
        $this->parentProductIdCache[$product->getId()] = $parentProductIds;
    }

    /**
     * Gets the variations / SKUs of configurable product as an associative array.
     *
     * @param Product $product
     * @param Store $store
     * @return array
     * @throws NoSuchEntityException
     */
    public function getSkusAsArray(Product $product, Store $store)
    {
        $inStockProductsByIds = $this->stockProvider->getInStockProductIds(
            $this->getSkuIds($product),
            $store->getWebsite()
        );
        return $this->skuResource->getSkuPricesByIds($store->getWebsite(), $inStockProductsByIds);
    }

    /**
     * Loads (or reloads) Product object
     * @param int $productId
     * @param int $storeId
     * @return ProductInterface|Product
     * @throws NoSuchEntityException
     */
    public function reloadProduct($productId, $storeId)
    {
        return $this->productRepository->getById(
            $productId,
            false,
            $storeId,
            true
        );
    }
}
