<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Service\Sync\Upsert\Category;

use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Store\Model\App\Emulation;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Scope as NostoScopeHelper;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory;
use Nosto\Tagging\Model\Service\Sync\AbstractBulkConsumer;
use Nosto\Tagging\Model\Service\Sync\Upsert\SyncService;

/**
 * Asynchronous Bulk Consumer
 *
 * Class AsyncBulkConsumer
 */
class AsyncBulkConsumer extends AbstractBulkConsumer
{
    /** @var SyncService */
    private SyncService $syncService;

    /** @var NostoScopeHelper */
    private NostoScopeHelper $nostoScopeHelper;

    /** @var CollectionFactory */
    private CollectionFactory $collectionFactory;

    /**
     * AsyncBulkConsumer constructor.
     * @param SyncService $syncService
     * @param NostoScopeHelper $nostoScopeHelper
     * @param CollectionFactory $collectionFactory
     * @param JsonHelper $jsonHelper
     * @param EntityManager $entityManager
     * @param Emulation $storeEmulation
     * @param Logger $logger
     */
    public function __construct(
        SyncService $syncService,
        NostoScopeHelper $nostoScopeHelper,
        CollectionFactory $collectionFactory,
        JsonHelper $jsonHelper,
        EntityManager $entityManager,
        Emulation $storeEmulation,
        Logger $logger
    ) {
        $this->syncService = $syncService;
        $this->nostoScopeHelper = $nostoScopeHelper;
        $this->collectionFactory = $collectionFactory;
        parent::__construct(
            $logger,
            $jsonHelper,
            $entityManager,
            $storeEmulation
        );
    }

    /**
     * @inheritDoc
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     */
    public function doOperation(array $categoryIds, string $storeId)
    {
        echo '<pre>';
        print_r(122);
        die;
        $store = $this->nostoScopeHelper->getStore($storeId);
        $categoryCollection = $this->collectionFactory->create()
            ->addIdsToFilter($categoryIds)
            ->addStoreFilter($storeId);
        $this->syncService->syncProducts($categoryCollection, $store);
    }
}
