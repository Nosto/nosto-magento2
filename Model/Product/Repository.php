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
use Nosto\Tagging\Helper\Data;
use Magento\Catalog\Model\Product\Type;

/**
 * Repository wrapper class for fetching products
 *
 * @package Nosto\Tagging\Model\Product
 */
class Repository
{
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
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Data $nostoDataHelper
     */
    public function __construct(
        ProductRepository $productRepository,
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
     * Gets the products scheduled for update
     * @param \DateTime $date
     * @return SearchResultInterface
     */
    public function getScheduledForUpdate(\DateTime $date)
    {
        $platformEdition
            = $this->nostoDataHelper->getPlatformEdition();
        switch (strtolower($platformEdition)) {
            case "enterprise":
                $field = 'news_from_date';
                break;
            default:
                $field = 'special_from_date';
        }
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter($field, $date->format('Y-m-d'), 'lt')
            ->setPageSize(5)
            ->setCurrentPage(1)
            ->create();
        $products = $this->productRepository->getList($searchCriteria);

        return $products;
    }

    /**
     * Gets the parent products for simple product
     * @return SearchResultInterface
     */
    public function resolveParentProducts(Product $product)
    {
        // ToDo - add caching fetched products here
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

        return $parentProducts;
    }
}
