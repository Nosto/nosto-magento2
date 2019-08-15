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

namespace Nosto\Tagging\Model\ResourceModel\Product\Index;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\Product\Index\Index;
use Nosto\Tagging\Model\ResourceModel\Product\Index as ResourceModelIndex;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            Index::class,
            ResourceModelIndex::class
        );
    }

    /**
     * @param Store $store
     * @return Collection
     */
    public function addStoreFilter(Store $store)
    {
        return $this->addFieldToFilter(Index::STORE_ID, ['eq' => $store->getId()]);
    }

    /**
     * @param array $ids
     * @return Collection
     */
    public function addIdsFilter(array $ids)
    {
        return $this->addFieldToFilter(
            Index::ID,
            ['in' => $ids]
        );
    }

    /**
     * Marks products as deleted by given product ids and store
     *
     * @param array $ids
     * @param Store $store
     * @return int
     */
    public function markAsDeleted(array $ids, Store $store)
    {
        if (empty($ids)) {
            return 0;
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IS_DELETED => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::PRODUCT_ID) => array_unique($ids),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Deletes current indexed products in store
     *
     * @param Store $store
     * @return int
     */
    public function deleteCurrentItemsByStore(Store $store)
    {
        if ($this->getSize() === 0) {
            return 0;
        }
        $indexIds = [];
        /* @var Index $item */
        foreach ($this->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $this->getConnection();
        return $connection->delete(
            $this->getMainTable(),
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }

    /**
     * Marks current items in collection as in_sync
     *
     * @param Store $store
     * @return int
     */
    public function markAsInSyncCurrentItemsByStore(Store $store)
    {
        if ($this->getSize() === 0) {
            return 0;
        }
        $indexIds = [];
        /* @var Index $item */
        foreach ($this->getItems() as $item) {
            $indexIds[] = $item->getId();
        }
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [Index::IN_SYNC => Index::DB_VALUE_BOOLEAN_TRUE],
            [
                sprintf('%s IN (?)', Index::ID) => array_unique($indexIds),
                sprintf('%s=?', Index::STORE_ID) => $store->getId()
            ]
        );
    }
}
