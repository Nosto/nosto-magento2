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

namespace Nosto\Tagging\Model\Indexer;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Index as NostoServiceIndex;

/**
 * Class Invalidate
 * This class is responsible for listening to product changes
 * and setting the `is_dirty` value in `nosto_product_index` table
 * @package Nosto\Tagging\Model\Indexer
 */
class Invalidate implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'nosto_index_product_invalidate';

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoLogger */
    private $logger;

    /** @var NostoServiceIndex */
    private $nostoServiceIndex;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /**
     * Dirty constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     * @param NostoServiceIndex $nostoServiceIndex
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger,
        NostoServiceIndex $nostoServiceIndex,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @param int[] $ids
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    public function execute($ids)
    {
        $ids = array_unique($ids);
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $productCollection = $this->getCollection($store, $ids);
            $this->nostoServiceIndex->handleProductChange($productCollection, $store);
            $collectionSize = $productCollection->getSize();
            $idsSize = count(array_unique($ids));
            if ($idsSize > $collectionSize) {
                $this->nostoServiceIndex->markProductsAsDeleted($productCollection, $ids, $store);
            }
        }
    }

    public function executeFull()
    {
        //ToDo - don't do anything here - we don't want this to be executed every time setup:upgrade is ran
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $productCollection = $this->getCollection($store);
            $this->nostoServiceIndex->handleProductChange($productCollection, $store);
        }
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return ProductCollection
     */
    private function getCollection(Store $store, array $ids = [])
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect($collection->getIdFieldName())
            ->addAttributeToFilter(
                'status',
                ['eq'=> Status::STATUS_ENABLED]
            )->addAttributeToFilter(
                'visibility',
                ['neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
            )->addStoreFilter($store);
        if (!empty($ids)) {
            $collection->addAttributeToFilter($collection->getIdFieldName(), ['in', $ids]);
        }
        return $collection;
    }
}
