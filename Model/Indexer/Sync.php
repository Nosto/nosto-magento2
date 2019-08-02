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

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Index\Index as NostoIndex;
use Nosto\Tagging\Model\Product\QueueRepository as NostoQueueRepository;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\Service\Index as NostoServiceIndex;

/**
 * An indexer for Nosto product sync
 */
class Sync implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'nosto_index_product_sync';

    /** @var NostoLogger */
    private $logger;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoServiceIndex */
    private $nostoServiceIndex;

    /** @var IndexCollectionFactory */
    private $indexCollectionFactory;

    /**
     * Sync constructor.
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param NostoQueueRepository $nostoQueueRepository
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoServiceIndex $nostoServiceIndex
     * @param IndexCollectionFactory $indexCollectionFactory
     */
    public function __construct(
        NostoLogger $logger,
        NostoHelperAccount $nostoHelperAccount,
        NostoServiceIndex $nostoServiceIndex,
        IndexCollectionFactory $indexCollectionFactory
    ) {
        $this->logger = $logger;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->indexCollectionFactory = $indexCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $indexCollection = $this->getCollection($store);
            $this->nostoServiceIndex->handleProductSync($indexCollection, $store);
        }
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     */
    public function execute($ids)
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $collection = $this->getCollection($store, $ids);
            $this->nostoServiceIndex->handleProductSync($collection, $store);
        }
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return IndexCollection
     */
    private function getCollection(Store $store, array $ids = [])
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::IS_DIRTY,
                ['eq' => NostoIndex::VALUE_IS_NOT_DIRTY]
            )->addFieldToFilter(
                NostoIndex::IN_SYNC,
                ['eq' => NostoIndex::VALUE_NOT_IN_SYNC]
            )->addFieldToFilter(
                NostoIndex::STORE_ID,
                ['eq' => $store->getId()]
            );
        if (!empty($ids)) {
            $collection->addFieldToFilter(
                NostoIndex::ID,
                ['in' => $ids]
            );
        }
        return $collection;
    }
}