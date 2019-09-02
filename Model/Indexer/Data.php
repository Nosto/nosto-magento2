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

use Exception;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Product\Index\Index as NostoIndex;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Store\Model\Store;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Exception\MemoryOutOfBoundsException;

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

    /** @var NostoLogger */
    private $nostoLogger;

    /**
     * @param NostoIndexService $nostoServiceIndex
     * @param IndexCollectionFactory $indexCollectionFactory
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $nostoLogger
     */
    public function __construct(
        NostoIndexService $nostoServiceIndex,
        IndexCollectionFactory $indexCollectionFactory,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $nostoLogger
    ) {
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->indexCollectionFactory = $indexCollectionFactory;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoLogger = $nostoLogger;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeFull()
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $indexCollection = $this->getCollection($store);
            try {
                $this->nostoServiceIndex->handleDirtyProducts($indexCollection, $store);
            } catch (MemoryOutOfBoundsException $e) {
                $this->nostoLogger->error($e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function execute($ids)
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            $collection = $this->getCollection($store, $ids);
            try {
                $this->nostoServiceIndex->handleDirtyProducts($collection, $store);
            } catch (MemoryOutOfBoundsException $e) {
                $this->nostoLogger->error($e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Returns a collection Nosto product index items that are dirty or out of sync and not deleted.
     * If $ids attribute is present the collection will be limited to matching the ids and the
     * condition mentioned above only.
     *
     * @param Store $store
     * @param array $ids
     * @return IndexCollection
     */
    private function getCollection(Store $store, array $ids = [])
    {
        $collection = $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                [NostoIndex::IN_SYNC, NostoIndex::IS_DIRTY],
                [NostoIndex::DB_VALUE_BOOLEAN_FALSE, NostoIndex::DB_VALUE_BOOLEAN_TRUE] // This is an OR condition
            )->addFieldToFilter(
                NostoIndex::IS_DELETED,
                ['eq' => NostoIndex::DB_VALUE_BOOLEAN_FALSE]
            )->addStoreFilter($store);
        if (!empty($ids)) {
            $collection->addIdsFilter($ids);
        }
        return $collection;
    }
}
