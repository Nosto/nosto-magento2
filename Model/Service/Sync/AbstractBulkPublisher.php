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

namespace Nosto\Tagging\Model\Service\Sync;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Nosto\Tagging\Logger\Logger;

abstract class AbstractBulkPublisher implements BulkPublisherInterface
{
    const STATUS_TYPE_OPEN = 4; //\Magento\Framework\Bulk\OperationInterface::STATUS_TYPE_OPEN;

    /** @var \Magento\Framework\Bulk\BulkManagementInterface|null */
    private $bulkManagement;

    /** @var \Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory|null */
    private $operationFactory;

    /** @var IdentityGeneratorInterface */
    private $identityService;

    /** @var SerializerInterface */
    public $serializer;

    /** @var BulkConsumerInterface */
    private $asyncBulkConsumer;

    /** @var Manager */
    private $manager;

    /**
     * AbstractBulkPublisher constructor.
     * @param IdentityGeneratorInterface $identityService
     * @param SerializerInterface $serializer
     * @param BulkConsumerInterface $asyncBulkConsumer
     * @param Manager $manager
     * @param Logger $logger
     */
    public function __construct(// @codingStandardsIgnoreLine
        IdentityGeneratorInterface $identityService,
        SerializerInterface $serializer,
        BulkConsumerInterface $asyncBulkConsumer,
        Manager $manager,
        Logger $logger
    ) {
        $this->identityService = $identityService;
        $this->serializer = $serializer;
        $this->asyncBulkConsumer = $asyncBulkConsumer;
        $this->manager = $manager;
        try {
            $this->bulkManagement = ObjectManager::getInstance()
                ->get(\Magento\Framework\Bulk\BulkManagementInterface::class);
            $this->operationFactory = ObjectManager::getInstance()
                ->get(\Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory::class);
        } catch (Exception $e) {
            $logger->debug('Module Magento_AsynchronousOperations not available');
        }
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function execute($storeId, $productIds = [])
    {
        if (!empty($productIds)) {
            $this->publishCollectionToQueue($storeId, $productIds);
        }
    }

    /**
     * @param $storeId
     * @param $productIds
     * @throws LocalizedException
     * @throws Exception
     */
    private function publishCollectionToQueue(
        $storeId,
        $productIds
    ) {
        $productIdsChunks = array_chunk($productIds, $this->getBulkSize());
        $bulkUuid = $this->identityService->generateId();
        /** @phan-suppress-next-line PhanTypeMismatchArgument */
        $bulkDescription = __('Sync ' . count($productIds) . ' Nosto products');
        $operationsData = [];
        foreach ($productIdsChunks as $productIdsChunk) {
            $operationsData[] = $this->buildOperationData(
                $storeId,
                $productIdsChunk,
                $bulkUuid
            );
        }
        if ($this->canUseAsyncOperations()) {
            $operations = [];
            foreach ($operationsData as $operationData) {
                $operations[] = $this->operationFactory->create($operationData);
            }
            if (empty($operations)) {
                return;
            }
            $result = $this->bulkManagement->scheduleBulk(
                $bulkUuid,
                $operations,
                $bulkDescription,
                UserContextInterface::USER_TYPE_INTEGRATION
            );
            if (!$result) {
                /** @phan-suppress-next-line PhanTypeMismatchArgument */
                throw new LocalizedException(__('Something went wrong while processing the request.'));
            }
        } else {
            foreach ($operationsData as $operationData) {
                $this->asyncBulkConsumer->processOperation($operationData);
            }
            return;
        }
    }

    /**
     * @return bool
     */
    private function canUseAsyncOperations(): bool
    {
        if ($this->manager->isEnabled('Magento_AsynchronousOperations')) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    abstract public function getTopicName(): string;

    /**
     * @return int
     */
    abstract public function getBulkSize(): int;

    /**
     * @return string
     */
    abstract public function getBulkDescription(): string;

    /**
     * @return string
     */
    abstract public function getMetaData(): string;

    /**
     * Build asynchronous operation data
     * @param int $storeId
     * @param array $productIds
     * @param string $bulkUuid
     * @return array
     */
    private function buildOperationData(
        $storeId,
        $productIds,
        $bulkUuid
    ) {
        $dataToEncode = [
            'meta_information' => $this->getMetaData(),
            'product_ids' => $productIds,
            'store_id' => $storeId
        ];
        return [
            'data' => [
                'bulk_uuid' => $bulkUuid,
                'topic_name' => $this->getTopicName(),
                'serialized_data' => $this->serializer->serialize($dataToEncode),
                'status' => self::STATUS_TYPE_OPEN
            ]
        ];
    }
}
