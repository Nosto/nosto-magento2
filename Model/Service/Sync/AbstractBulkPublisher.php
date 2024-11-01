<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

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
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Serialize\SerializerInterface;
use Nosto\Tagging\Logger\Logger;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;

abstract class AbstractBulkPublisher implements BulkPublisherInterface
{
    private const STATUS_TYPE_OPEN = \Magento\Framework\Bulk\OperationInterface::STATUS_TYPE_OPEN;

    /** @var \Magento\Framework\Bulk\BulkManagementInterface|null */
    private $bulkManagement;

    /** @var OperationInterfaceFactory|null */
    private ?OperationInterfaceFactory $operationFactory;

    /** @var IdentityGeneratorInterface */
    private IdentityGeneratorInterface $identityService;

    /** @var SerializerInterface */
    public SerializerInterface $serializer;

    /** @var Manager */
    private Manager $manager;

    /** @var Logger */
    private Logger $logger;

    /**
     * AbstractBulkPublisher constructor.
     * @param IdentityGeneratorInterface $identityService
     * @param OperationInterfaceFactory $operationInterfaceFactory
     * @param SerializerInterface $serializer
     * @param Manager $manager
     * @param Logger $logger
     */
    public function __construct(// @codingStandardsIgnoreLine
        IdentityGeneratorInterface $identityService,
        OperationInterfaceFactory $operationInterfaceFactory,
        SerializerInterface $serializer,
        Manager $manager,
        Logger $logger
    ) {
        $this->identityService = $identityService;
        $this->operationFactory = $operationInterfaceFactory;
        $this->serializer = $serializer;
        $this->manager = $manager;
        $this->logger = $logger;
        try {
            $this->bulkManagement = ObjectManager::getInstance()
                ->get(\Magento\Framework\Bulk\BulkManagementInterface::class);
        } catch (Exception $e) {
            $logger->debug('Module Magento_AsynchronousOperations not available');
        }
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function execute(int $storeId, array $entityIds = [])
    {
        if (!empty($entityIds)) {
            $this->publishCollectionToMessageQueue($storeId, $entityIds);
        }
    }

    /**
     * @param $storeId
     * @param $entityIds
     * @throws LocalizedException
     * @throws Exception
     */
    private function publishCollectionToMessageQueue(
        $storeId,
        $entityIds
    ) {
        if (!$this->canUseAsyncOperations()) {
            $this->logger->critical(
                "Module Magento_AsynchronousOperations not available. Aborting bulk publish operation"
            );
            return;
        }
        $productIdsChunks = array_chunk($entityIds, $this->getBulkSize());
        $bulkUuid = $this->identityService->generateId();
        /**
         * Argument is of type string but array is expected
         */
        /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
        $bulkDescription = __('Sync ' . count($entityIds) . ' Nosto products');
        $operationsData = [];
        foreach ($productIdsChunks as $productIdsChunk) {
            $operationsData[] = $this->buildOperationData(
                $storeId,
                $productIdsChunk,
                $bulkUuid
            );
        }

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
            $bulkDescription
        );
        if (!$result) {
            $msg = 'Something went wrong while publishing bulk to RabbitMQ. Please check your connection';
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            throw new LocalizedException(__($msg));
        }
    }

    /**
     * @return bool
     */
    private function canUseAsyncOperations(): bool
    {
        return $this->manager->isEnabled('Magento_AsynchronousOperations');
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
        int $storeId,
        array $productIds,
        string $bulkUuid
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
