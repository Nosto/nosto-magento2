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

namespace Nosto\Tagging\Model\Service\Sync;

use Exception;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\Service\Sync\SyncService as NostoSyncService;
use Nosto\Tagging\Helper\Scope as NostoScopeHelper;

/**
 * Asynchronous Bulk Consumer
 *
 * Class AsyncBulkConsumer
 */
class AsyncBulkConsumer
{
    /** @var Logger */
    private $logger;

    /** @var JsonHelper */
    private $jsonHelper;

    /** @var IndexRepository */
    private $indexRepository;

    /** @var NostoSyncService */
    private $nostoSyncService;

    /** @var NostoScopeHelper */
    private $nostoScopeHelper;

    /** @var EntityManager */
    private $entityManager;

    /**
     * AsyncBulkConsumer constructor.
     *
     * @param Logger $logger
     * @param JsonHelper $jsonHelper
     * @param IndexRepository $indexRepository
     * @param NostoSyncService $nostoSyncService
     * @param NostoScopeHelper $nostoScopeHelper
     * @param EntityManager $entityManager
     */
    public function __construct(
        Logger $logger,
        JsonHelper $jsonHelper,
        IndexRepository $indexRepository,
        NostoSyncService $nostoSyncService,
        NostoScopeHelper $nostoScopeHelper,
        EntityManager $entityManager
    ) {
        $this->logger = $logger;
        $this->jsonHelper = $jsonHelper;
        $this->indexRepository = $indexRepository;
        $this->nostoSyncService = $nostoSyncService;
        $this->nostoScopeHelper = $nostoScopeHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Processing operation for product sync
     *
     * @param array|\Magento\AsynchronousOperations\Api\Data\OperationInterface $operation
     * @return void
     * @throws Exception
     */
    public function processOperation($operation)
    {
        $errorCode = null;
        $message = null;
        if (!is_array($operation)) {
            $serializedData = $operation->getSerializedData();
        } else {
            $serializedData = $operation['data']['serialized_data'];
        }
        $unserializedData = $this->jsonHelper->jsonDecode($serializedData);
        $productIds = $unserializedData['product_ids'];
        $storeId = $unserializedData['store_id'];
        try {
            $store = $this->nostoScopeHelper->getStore($storeId);
            $outOfSyncCollection = $this->indexRepository->getByProductIdsAndStoreId($productIds, $storeId);
            $this->nostoSyncService->syncIndexedProducts($outOfSyncCollection, $store);
            $this->nostoSyncService->syncDeletedProducts($store);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            $message = __('Something went wrong when syncing products to Nosto. Check log for details.');
            if (!is_array($operation)) {
                $operation->setStatus(OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED)
                    ->setErrorCode($e->getCode())
                    ->setResultMessage($message);
            }
        }
        if (!is_array($operation)) {
            $operation->setStatus(OperationInterface::STATUS_TYPE_COMPLETE)
                ->setResultMessage($message);
            $this->entityManager->save($operation);
        }
    }
}
