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

namespace Nosto\Tagging\Model\Product\Sync;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Model\OperationManagement;
use Magento\Framework\Json\Helper\Data;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\Service\Sync as NostoSyncService;
use Nosto\Tagging\Helper\Scope as NostoScopeHelper;

/**
 * Class Consumer
 */
class Consumer
{
    /** @var Logger */
    private $logger;

    /** @var Data */
    private $jsonHelper;

    /** @var OperationManagement */
    private $operationManagement;

    /** @var IndexRepository */
    private $indexRepository;

    /** @var NostoSyncService */
    private $nostoSyncService;

    /** @var NostoScopeHelper */
    private $nostoScopeHelper;

    /**
     * Consumer constructor.
     *
     * @param Logger $logger
     * @param Data $jsonHelper
     * @param OperationManagement $operationManagement
     * @param IndexRepository $indexRepository
     * @param NostoSyncService $nostoSyncService
     * @param NostoScopeHelper $nostoScopeHelper
     */
    public function __construct(
        Logger $logger,
        Data $jsonHelper,
        OperationManagement $operationManagement,
        IndexRepository $indexRepository,
        NostoSyncService $nostoSyncService,
        NostoScopeHelper $nostoScopeHelper
    ) {
        $this->logger = $logger;
        $this->jsonHelper = $jsonHelper;
        $this->operationManagement = $operationManagement;
        $this->indexRepository = $indexRepository;
        $this->nostoSyncService = $nostoSyncService;
        $this->nostoScopeHelper = $nostoScopeHelper;
    }

    /**
     * Processing operation for product sync
     *
     * @param OperationInterface $operation
     * @return void
     */
    public function processOperation(OperationInterface $operation)
    {
        $status = OperationInterface::STATUS_TYPE_COMPLETE;
        $errorCode = null;
        $message = null;
        $serializedData = $operation->getSerializedData();
        $unserializedData = $this->jsonHelper->jsonDecode($serializedData);
        $productIds = $unserializedData['product_ids'];
        $storeId = $unserializedData['store_id'];
        try {
            $store = $this->nostoScopeHelper->getStore($storeId);
            $outOfSyncCollection = $this->indexRepository->getByProductIdsAndStoreId($productIds, $storeId);
            $this->nostoSyncService->syncIndexedProducts($outOfSyncCollection, $store);
            $this->nostoSyncService->syncDeletedProducts($store);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $status = OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED;
            $errorCode = $e->getCode();
            $message = __('Something went wrong when syncing products to Nosto. Check log for details.');
        }
        $this->operationManagement->changeOperationStatus(
            $operation->getId(),
            $status,
            $errorCode,
            $message,
            $serializedData
        );
    }
}
