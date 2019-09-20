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

namespace Nosto\Tagging\Model\Service;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Bulk\BulkManagementInterface;
use Magento\Framework\Bulk\OperationInterface as BulkOperationInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;

class SyncBulkPublisher
{
    const NOSTO_SYNC_MESSAGE_QUEUE = 'nosto_product_sync.update';
    const BULK_SIZE = 100;

    /** @var BulkManagementInterface */
    private $bulkManagement;

    /** @var OperationInterfaceFactory */
    private $operationFactory;

    /** @var IdentityGeneratorInterface */
    private $identityService;

    /** @var SerializerInterface */
    private $serializer;

    /** @var UserContextInterface */
    private $userContext;

    /**
     * SyncBulkPublisher constructor.
     * @param BulkManagementInterface $bulkManagement
     * @param OperationInterfaceFactory $operationFactory
     * @param IdentityGeneratorInterface $identityService
     * @param SerializerInterface $serializer
     * @param UserContextInterface $userContext
     */
    public function __construct(
        BulkManagementInterface $bulkManagement,
        OperationInterfaceFactory $operationFactory,
        IdentityGeneratorInterface $identityService,
        SerializerInterface $serializer,
        UserContextInterface $userContext
    ) {
        $this->bulkManagement = $bulkManagement;
        $this->operationFactory = $operationFactory;
        $this->identityService = $identityService;
        $this->serializer = $serializer;
        $this->userContext = $userContext;
    }

    /**
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws LocalizedException
     */
    public function publishCollectionToQueue(NostoIndexCollection $collection, Store $store)
    {
        $productIds = $collection->walk('getProductId');
        $this->publish($store->getId(), $productIds);
    }

    /**
     * @param $storeId
     * @param $productIds
     * @throws LocalizedException
     */
    private function publish(
        $storeId,
        $productIds
    ) {
        $productIdsChunks = array_chunk($productIds, self::BULK_SIZE);
        $bulkUuid = $this->identityService->generateId();
        $bulkDescription = __('Sync ' . count($productIds) . ' Nosto products');
        $operations = [];
        foreach ($productIdsChunks as $productIdsChunk) {
            $operations[] = $this->makeOperation(
                'Sync Nosto products',
                self::NOSTO_SYNC_MESSAGE_QUEUE,
                $storeId,
                $productIdsChunk,
                $bulkUuid
            );
        }

        if (!empty($operations)) {
            $result = $this->bulkManagement->scheduleBulk(
                $bulkUuid,
                $operations,
                $bulkDescription,
                $this->userContext->getUserId()
            );
            if (!$result) {
                throw new LocalizedException(
                    __('Something went wrong while processing the request.')
                );
            }
        }
    }

    /**
     * Make asynchronous operation
     *
     * @param string $meta
     * @param string $queue
     * @param int $storeId
     * @param array $productIds
     * @param string $bulkUuid
     *
     * @return OperationInterface
     */
    private function makeOperation(
        $meta,
        $queue,
        $storeId,
        $productIds,
        $bulkUuid
    ) {
        $dataToEncode = [
            'meta_information' => $meta,
            'product_ids' => $productIds,
            'store_id' => $storeId
        ];
        $data = [
            'data' => [
                'bulk_uuid' => $bulkUuid,
                'topic_name' => $queue,
                'serialized_data' => $this->serializer->serialize($dataToEncode),
                'status' => BulkOperationInterface::STATUS_TYPE_OPEN,
            ]
        ];

        return $this->operationFactory->create($data);
    }
}
