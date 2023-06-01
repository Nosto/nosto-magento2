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

namespace Nosto\Tagging\Model\ProductIndexerIgnorance;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\Api\Data\ProductIndexerIgnoranceInterface;
use Nosto\Tagging\Api\Data\ProductIndexerIgnoranceSearchResultsInterface;
use Nosto\Tagging\Model\ProductIndexerIgnorance\ProductIndexerIgnoranceSearchResultFactory as SearchResultFactory;
use Nosto\Tagging\Api\ProductIndexerIgnoranceRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\ProductIndexerIgnorance as ProductIndexerIgnoranceResource;
use Nosto\Tagging\Model\ResourceModel\ProductIndexerIgnorance\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Nosto\Tagging\Util\Repository as RepositoryUtil;
use Magento\Framework\Api\SearchResultsInterfaceFactory;

class Repository implements ProductIndexerIgnoranceRepositoryInterface
{
    /**
     * @var ProductIndexerIgnoranceResource
     */
    protected $resource;

    /**
     * @var ProductIndexerIgnoranceFactory
     */
    protected $productIndexerIgnoranceFactory;

    /**
     * @var ProductIndexerIgnoranceCollectionFactory
     */
    protected $productIndexerIgnoranceCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * ProductIndexerIgnoranceRepository constructor.
     * @param ProductIndexerIgnoranceResource $resource
     * @param ProductIndexerIgnoranceFactory $productIndexerIgnoranceFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ProductIndexerIgnoranceResource $resource,
        ProductIndexerIgnoranceFactory  $productIndexerIgnoranceFactory,
        CollectionFactory               $collectionFactory,
        SearchResultFactory             $searchResultsFactory,
        CollectionProcessorInterface    $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->productIndexerIgnoranceFactory = $productIndexerIgnoranceFactory;
        $this->productIndexerIgnoranceCollectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Save the product indexer ignorance.
     *
     * @param ProductIndexerIgnoranceInterface $productIndexerIgnorance
     * @return ProductIndexerIgnoranceInterface
     * @throws LocalizedException
     */
    public function save(ProductIndexerIgnoranceInterface $productIndexerIgnorance)
    {
        $this->resource->save($productIndexerIgnorance);
        return $productIndexerIgnorance;
    }

    /**
     * Retrieve a product indexer ignorance by ID.
     *
     * @param int $id
     * @return ProductIndexerIgnoranceInterface
     * @throws LocalizedException
     */
    public function getById($id)
    {
        $productIndexerIgnorance = $this->productIndexerIgnoranceFactory->create();
        $this->resource->load($productIndexerIgnorance, $id);
        if (!$productIndexerIgnorance->getId()) {
            throw new NoSuchEntityException(__('Product indexer ignorance with ID "%1" does not exist.', $id));
        }
        return $productIndexerIgnorance;
    }

    /**
     * Retrieve product indexer ignorance list based on search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return ProductIndexerIgnoranceSearchResultsInterface
     * @throws LocalizedException
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->productIndexerIgnoranceCollectionFactory->create();
        $searchResults = $this->searchResultsFactory->create();

        /**
         * Returning \Magento\Framework\Api\Search\SearchResult
         * but declared to return ProductIndexerIgnoranceSearchResultsInterface
         */
        /** @phan-suppress-next-next-line PhanTypeMismatchReturnSuperType */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return (new RepositoryUtil())->search(
            $collection,
            $searchCriteria,
            $searchResults
        );
    }

    /**
     * Delete a product indexer ignorance.
     *
     * @param ProductIndexerIgnoranceInterface $productIndexerIgnorance
     * @return bool
     * @throws LocalizedException
     */
    public function delete(ProductIndexerIgnoranceInterface $productIndexerIgnorance)
    {
        $this->resource->delete($productIndexerIgnorance);
        return true;
    }

    /**
     * Delete a product indexer ignorance by ID.
     *
     * @param int $id
     * @return bool
     * @throws LocalizedException
     */
    public function deleteById($id)
    {
        $productIndexerIgnorance = $this->getById($id);
        return $this->delete($productIndexerIgnorance);
    }

    /**
     * Delete a product indexer ignorance by IDs.
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
