<?php
/*
 * Copyright (c) 2023, Nosto Solutions Ltd
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
 * @copyright 2023 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 */

namespace Nosto\Tagging\Model\ProductIndexerExclude;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\Api\Data\ProductIndexerExcludeInterface;
use Nosto\Tagging\Api\Data\ProductIndexerExcludeSearchResultsInterface;
use Nosto\Tagging\Model\ProductIndexerExclude\ProductIndexerExcludeSearchResultFactory as SearchResultFactory;
use Nosto\Tagging\Api\ProductIndexerExcludeRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\ProductIndexerExclude as ResourceModel;
use Nosto\Tagging\Model\ResourceModel\ProductIndexerExclude\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Nosto\Tagging\Util\Repository as RepositoryUtil;
use Nosto\Tagging\Model\ProductIndexerExclude\ProductIndexerExcludeSearchResultFactory
    as SearchResultsInterfaceFactory;

class Repository implements ProductIndexerExcludeRepositoryInterface
{
    /**
     * @var ResourceModel
     */
    protected $resource;

    /**
     * @var ProductIndexerExcludeFactory
     */
    protected $productIndexerExcludeFactory;

    /**
     * @var CollectionFactory
     */
    protected $productIndexerExcludeCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * ProductIndexerExcludeRepository constructor.
     * @param ResourceModel $resource
     * @param ProductIndexerExcludeFactory $productIndexerExcludeFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceModel                  $resource,
        ProductIndexerExcludeFactory $productIndexerExcludeFactory,
        CollectionFactory              $collectionFactory,
        SearchResultFactory            $searchResultsFactory,
        CollectionProcessorInterface   $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->productIndexerExcludeFactory = $productIndexerExcludeFactory;
        $this->productIndexerExcludeCollectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Save the product indexer Exclude.
     *
     * @param ProductIndexerExcludeInterface $productIndexerExclude
     * @return ProductIndexerExcludeInterface
     * @throws LocalizedException
     * @suppress PhanTypeMismatchArgument
     */
    public function save(ProductIndexerExcludeInterface $productIndexerExclude)
    {
        $this->resource->save($productIndexerExclude);
        return $productIndexerExclude;
    }

    /**
     * Retrieve a product indexer Exclude by ID.
     *
     * @param int $id
     * @return ProductIndexerExcludeInterface
     * @throws LocalizedException
     */
    public function getById($id)
    {
        $productIndexerExclude = $this->productIndexerExcludeFactory->create();
        $this->resource->load($productIndexerExclude, $id);
        if (!$productIndexerExclude->getId()) {
            // @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal, PhanTypeMismatchArgument
            throw new NoSuchEntityException(__('Product indexer Exclude with ID "%1" does not exist.', $id));
        }
        return $productIndexerExclude;
    }

    /**
     * Retrieve product indexer Exclude list based on search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return ProductIndexerExcludeSearchResultsInterface
     * @throws LocalizedException
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->productIndexerExcludeCollectionFactory->create();
        $searchResults = $this->searchResultsFactory->create();

        /**
         * Returning \Magento\Framework\Api\Search\SearchResult
         * but declared to return ProductIndexerExcludeSearchResultsInterface
         */
        /** @phan-suppress-next-next-line PhanTypeMismatchReturn */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return (new RepositoryUtil())->search(
            $collection,
            $searchCriteria,
            $searchResults
        );
    }

    /**
     * Delete a product indexer Exclude.
     *
     * @param ProductIndexerExcludeInterface $productIndexerExclude
     * @return bool
     * @throws LocalizedException
     * @suppress PhanTypeMismatchArgument
     */
    public function delete(ProductIndexerExcludeInterface $productIndexerExclude)
    {
        $this->resource->delete($productIndexerExclude);
        return true;
    }

    /**
     * Delete a product indexer Exclude by ID.
     *
     * @param int $id
     * @return bool
     * @throws LocalizedException
     */
    public function deleteById($id)
    {
        $productIndexerExclude = $this->getById($id);
        return $this->delete($productIndexerExclude);
    }

    /**
     * Delete a product indexer Exclude by IDs.
     *
     * @param array $ids
     * @return bool
     * @throws LocalizedException
     */
    public function deleteByIds($ids)
    {
        if (empty($ids)) {
            return false;
        }

        $this->resource->getConnection()->delete(
            $this->resource->getMainTable(),
            ['entity_id IN (?)' => $ids]
        );
    }
}
