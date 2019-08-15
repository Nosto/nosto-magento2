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

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Product\Index\Index as NostoIndex;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Store\Model\Store;

/**
 * An indexer for Nosto product sync
 */
class Data implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'nosto_index_product_data_sync';

    /** @var NostoIndexService */
    private $nostoServiceIndex;

    /** @var IndexCollectionFactory */
    private $indexCollectionFactory;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /**
     * @param NostoIndexService $nostoServiceIndex
     * @param IndexCollectionFactory $indexCollectionFactory
     * @param NostoHelperAccount $nostoHelperAccount
     */
    public function __construct(
        NostoIndexService $nostoServiceIndex,
        IndexCollectionFactory $indexCollectionFactory,
        NostoHelperAccount $nostoHelperAccount
    ) {
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->indexCollectionFactory = $indexCollectionFactory;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        $dirtyCollection = $this->getDirtyCollection();
        $this->nostoServiceIndex->handleDirtyProducts($dirtyCollection);
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        $outOfSyncCollection = $this->getOutOfSyncCollection();
        foreach ($storesWithNosto as $store) {
            $this->nostoServiceIndex->handleProductSync($outOfSyncCollection, $store);
        }
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute($ids)
    {
        $collection = $this->getDirtyCollection($ids);
        $this->nostoServiceIndex->handleDirtyProducts($collection);
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $outOfSyncCollection = $this->getOutOfSyncCollection($store, $ids);
            $this->nostoServiceIndex->handleProductSync($outOfSyncCollection, $store);
        }
    }

    /**
     * Returns a collection Nosto product index items that are dirty and not deleted.
     * If $ids attribute is present the collection will be limited to matching the ids and the
     * condition mentioned above only.
     *
     * @param array $ids array of product index ids (not product id)
     * @return IndexCollection
     */
    private function getDirtyCollection(array $ids = [])
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::IS_DIRTY,
                ['eq' => NostoIndex::DB_VALUE_BOOLEAN_TRUE]
            )->addFieldToFilter(
                NostoIndex::IS_DELETED,
                ['eq' => NostoIndex::DB_VALUE_BOOLEAN_FALSE]
            );
        if (!empty($ids)) {
            $collection->addIdsFilter($ids);
        }
        return $collection;
    }

    /**
     * Returns a collection Nosto product index items that are out of sync and not deleted.
     * If $ids attribute is present the collection will be limited to matching the ids and the
     * condition mentioned above only.
     *
     * @param Store $store
     * @param array $ids
     * @return IndexCollection
     */
    private function getOutOfSyncCollection(Store $store = null, array $ids = [])
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                NostoIndex::IN_SYNC,
                ['eq' => NostoIndex::DB_VALUE_BOOLEAN_FALSE]
            )->addFieldToFilter(
                NostoIndex::IS_DELETED,
                ['eq' => NostoIndex::DB_VALUE_BOOLEAN_FALSE]
            );
        if ($store) {
            $collection->addStoreFilter($store);
        }
        if (!empty($ids)) {
            $collection->addIdsFilter($ids);
        }
        return $collection;
    }
}
