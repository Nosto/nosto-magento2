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

namespace Nosto\Tagging\Cron;

use Exception;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Model\Service\Sync;
use Magento\Store\Model\Store;

/**
 * Cronjob class that periodically sends indexed product data to Nosto
 *
 * @package Nosto\Tagging\Cron
 */
class Upsert
{
    /** @var IndexCollectionFactory */
    private $indexCollectionFactory;

    /** @var NostoAccountHelper */
    private $nostoAccountHelper;

    /** @var Sync */
    private $sync;

    /**
     * Invalidate constructor.
     *
     * @param IndexCollectionFactory $indexCollectionFactory
     * @param NostoAccountHelper $nostoAccountHelper
     * @param Sync $sync
     */
    public function __construct(
        IndexCollectionFactory $indexCollectionFactory,
        NostoAccountHelper $nostoAccountHelper,
        Sync $sync
    ) {
        $this->indexCollectionFactory = $indexCollectionFactory;
        $this->nostoAccountHelper = $nostoAccountHelper;
        $this->sync = $sync;
    }

    /**
     * Executes cron job
     * @throws Exception
     */
    public function execute()
    {
        $stores = $this->nostoAccountHelper->getStoresWithNosto();
        foreach ($stores as $store) {
            $productIndexCollection = $this->getCollection($store);
            $this->sync->syncIndexedProducts($productIndexCollection, $store);
        }
    }

    /**
     * @param Store $store
     * @return NostoIndexCollection
     * @throws Exception
     */
    private function getCollection(Store $store)
    {
        return $this->indexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addOutOfSyncFilter()
            ->addStoreFilter($store)
            ->limitResults(1000);
    }
}
