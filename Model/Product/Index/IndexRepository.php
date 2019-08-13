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

namespace Nosto\Tagging\Model\Product\Index;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\Search\SearchResult;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Api\ProductIndexRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\Product\Index as IndexResource;

use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Util\Repository as RepositoryUtil;

class IndexRepository implements ProductIndexRepositoryInterface
{
    private $searchCriteriaBuilder;
    private $indexCollectionFactory;
    private $indexSearchResultsFactory;
    private $indexResource;

    /**
     * IndexRepository constructor.
     *
     * @param IndexResource $indexResource
     * @param IndexCollectionFactory $indexCollectionFactory
     * @param IndexSearchResultsFactory $indexSearchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        IndexResource $indexResource,
        IndexCollectionFactory $indexCollectionFactory,
        IndexSearchResultsFactory $indexSearchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->indexResource = $indexResource;
        $this->indexCollectionFactory = $indexCollectionFactory;
        $this->indexSearchResultsFactory = $indexSearchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Returns all entries by product ids
     *
     * @param int $productId
     *
     * @return SearchResult
     */
    public function getByProductId($productId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductIndexInterface::PRODUCT_ID, $productId, 'eq')
            ->create();

        return $this->search($searchCriteria);
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductAndStore(ProductInterface $product, StoreInterface $store)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductIndexInterface::PRODUCT_ID, $product->getId(), 'eq')
            ->addFilter(ProductIndexInterface::STORE_ID, $store->getId(), 'eq')
            ->setPageSize(1)
            ->create();

        /* @var IndexSearchResults $results */
        $results = $this->search($searchCriteria);

        return $results->getFirstItem();
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductIndexInterface::ID, $id, 'eq')
            ->setPageSize(1)
            ->create();

        /* @var IndexSearchResults $results */
        $results = $this->search($searchCriteria);

        return $results->getFirstItem();
    }

    /**
     * @inheritdoc
     */
    public function getByIds(array $ids)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductIndexInterface::ID, $ids, 'in')
            ->create();
        /* @var IndexSearchResults $results */
        $results = $this->search($searchCriteria);

        return $results->getItems();
    }

    /**
     * @inheritdoc
     */
    public function getByProductIdAndStoreId(int $productId, int $storeId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ProductIndexInterface::PRODUCT_ID, $productId, 'eq')
            ->addFilter(ProductIndexInterface::STORE_ID, $storeId, 'eq')
            ->setPageSize(1)
            ->create();

        /* @var IndexSearchResults $results */
        $results = $this->search($searchCriteria);

        return $results->getFirstItem();
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return IndexSearchResults
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->indexCollectionFactory->create();
        $searchResults = $this->indexSearchResultsFactory->create();

        return RepositoryUtil::search(
            $collection,
            $searchCriteria,
            $searchResults
        );
    }

    /**
     * Save product index entry
     *
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface|IndexResource
     * @throws \Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function save(ProductIndexInterface $productIndex)
    {
        /** @noinspection PhpParamsInspection */
        /** @var AbstractModel $productIndex */
        return $this->indexResource->save($productIndex);
    }

    /**
     * Delete product index entry
     * @param ProductIndexInterface $productIndex
     * @throws \Exception
     * @suppress PhanTypeMismatchArgument
     */
    public function delete(ProductIndexInterface $productIndex)
    {
        /** @noinspection PhpParamsInspection */
        /** @var AbstractModel $productIndex */
        $this->indexResource->delete($productIndex);
    }
}
