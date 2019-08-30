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
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Api\ProductIndexRepositoryInterface;
use Nosto\Tagging\Model\Product\Index\Index as NostoIndex;
use Nosto\Tagging\Model\ResourceModel\Product\Index as IndexResource;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Util\Index as IndexUtil;
use Magento\Store\Model\Store;

class IndexRepository implements ProductIndexRepositoryInterface
{
    private $indexCollectionFactory;
    private $indexResource;

    /**
     * IndexRepository constructor.
     *
     * @param IndexResource $indexResource
     * @param IndexCollectionFactory $indexCollectionFactory
     */
    public function __construct(
        IndexResource $indexResource,
        IndexCollectionFactory $indexCollectionFactory
    ) {
        $this->indexResource = $indexResource;
        $this->indexCollectionFactory = $indexCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function getOneByProductAndStore(ProductInterface $product, StoreInterface $store)
    {
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::PRODUCT_ID,
                ['eq' => $product->getId()]
            )
            ->addStoreFilter($store)
            ->setPageSize(1)
            ->setCurPage(1);
        return IndexUtil::nullableFirstItem($collection);
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::ID,
                ['eq' => $id]
            )
            ->setPageSize(1)
            ->setCurPage(1);
        return IndexUtil::nullableFirstItem($collection);
    }

    /**
     * @inheritdoc
     */
    public function getTotalOutOfSync(Store $store)
    {
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create();
        $collection->addFilter(
            ProductIndexInterface::IN_SYNC,
            NostoIndex::DB_VALUE_BOOLEAN_FALSE,
            'eq'
        );
        if ((int)$store->getId() !== 0) {
            $collection->addStoreFilter($store);
        }
        return $collection->getSize();
    }

    /**
     * @inheritdoc
     */
    public function getTotalDirty(Store $store)
    {
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create();
        $collection->addFilter(
            ProductIndexInterface::IS_DIRTY,
            NostoIndex::DB_VALUE_BOOLEAN_TRUE,
            'eq'
        );
        if ((int)$store->getId() !== 0) {
            $collection->addStoreFilter($store);
        }
        return $collection->getSize();
    }
    /**
     * @inheritdoc
     */
    public function getByIds(array $ids)
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIdsFilter($ids)
            ->setPageSize(1)
            ->setCurPage(1);
        return $collection->getItems(); // @codingStandardsIgnoreLine
    }

    /**
     * @inheritdoc
     */
    public function getByProductIdAndStoreId(int $productId, int $storeId)
    {
        /* @var IndexCollection $collection */
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::PRODUCT_ID,
                ['eq' => $productId]
            )
            ->addFieldToFilter(
                NostoIndex::STORE_ID,
                ['eq' => $storeId]
            )
            ->setPageSize(1)
            ->setCurPage(1);
        return IndexUtil::nullableFirstItem($collection);
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
