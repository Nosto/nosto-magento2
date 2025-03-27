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
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Store\Model\App\Emulation;
use Nosto\Tagging\Logger\Logger;

abstract class AbstractBulkConsumer implements BulkConsumerInterface
{
    /** @var Logger */
    public Logger $logger;

    /** @var JsonHelper */
    private JsonHelper $jsonHelper;

    /** @var EntityManager */
    private EntityManager $entityManager;

    /** @var Emulation */
    private Emulation $storeEmulation;

    /**
     * AbstractBulkConsumer constructor.
     * @param Logger $logger
     * @param JsonHelper $jsonHelper
     * @param EntityManager $entityManager
     * @param Emulation $storeEmulation
     */
    public function __construct(
        Logger $logger,
        JsonHelper $jsonHelper,
        EntityManager $entityManager,
        Emulation $storeEmulation
    ) {
        $this->logger = $logger;
        $this->jsonHelper = $jsonHelper;
        $this->entityManager = $entityManager;
        $this->storeEmulation = $storeEmulation;
    }

    /**
     * Processing operation for product sync
     *
     * @param OperationInterface $operation
     * @return void
     * @throws Exception
     * @suppress PhanUndeclaredClassConstant
     */
    public function processOperation(OperationInterface $operation)
    {
        $serializedData = $operation->getSerializedData();
        $unserializedData = $this->jsonHelper->jsonDecode($serializedData);

        $entityIds = $unserializedData['entity_ids'] ?? null;
        $storeId = $unserializedData['store_id'];
        try {
            $this->storeEmulation->startEnvironmentEmulation((int)$storeId);
            $this->doOperation($entityIds, $storeId);
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            $message = __('Success.');
            $operation->setStatus(OperationInterface::STATUS_TYPE_COMPLETE)
                ->setResultMessage($message);
        } catch (Exception $e) {
            $this->logger->critical(sprintf('Bulk uuid: %s. %s', $operation->getBulkUuid(), $e->getMessage()));
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
            $message = __('Something went wrong when syncing data to Nosto. Check log for details.');
            $operation->setStatus(OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED)
                ->setErrorCode($e->getCode())
                ->setResultMessage($message);
        } finally {
            $this->entityManager->save($operation);
            $this->storeEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @param array $entityIds
     * @param string $storeId
     */
    abstract public function doOperation(array $entityIds, string $storeId);
}
