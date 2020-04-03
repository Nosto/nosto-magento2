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
use InvalidArgumentException;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Nosto\Tagging\Logger\Logger;

abstract class AbstractBulkConsumer implements BulkConsumerInterface
{
    /** @var Logger */
    private $logger;

    /** @var JsonHelper */
    private $jsonHelper;

    /** @var EntityManager */
    private $entityManager;

    /**
     * AbstractBulkConsumer constructor.
     * @param Logger $logger
     * @param JsonHelper $jsonHelper
     * @param EntityManager $entityManager
     */
    public function __construct(
        Logger $logger,
        JsonHelper $jsonHelper,
        EntityManager $entityManager
    ) {
        $this->logger = $logger;
        $this->jsonHelper = $jsonHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Processing operation for product sync
     *
     * @param array|\Magento\AsynchronousOperations\Api\Data\OperationInterface $operation
     * @return void
     * @throws Exception
     * @suppress PhanUndeclaredClassConstant
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function processOperation($operation)
    {
        $errorCode = null;
        if (is_array($operation)) {
            $serializedData = $operation['data']['serialized_data'];
        } elseif ($operation instanceof \Magento\AsynchronousOperations\Api\Data\OperationInterface) {
            $serializedData = $operation->getSerializedData();
        } else {
            throw new InvalidArgumentException(
                'Wrong type passed to AsyncBulkConsumer::processOperation. 
                Expected array|\Magento\AsynchronousOperations\Api\Data\OperationInterface.'
            );
        }
        $unserializedData = $this->jsonHelper->jsonDecode($serializedData);
        $productIds = $unserializedData['product_ids'];
        $storeId = $unserializedData['store_id'];
        try {
            $this->doOperation($productIds, $storeId);
            if (!is_array($operation)) {
                $message = __('Something went wrong when syncing products to Nosto. Check log for details.');
                $operation->setStatus(
                    \Magento\AsynchronousOperations\Api\Data\OperationInterface::STATUS_TYPE_COMPLETE
                )->setResultMessage($message);
                $this->entityManager->save($operation);
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            /** @phan-suppress-next-line PhanTypeMismatchArgument */
            $message = __('Something went wrong when syncing products to Nosto. Check log for details.');
            if (!is_array($operation)) {
                $operation->setStatus(
                    \Magento\AsynchronousOperations\Api\Data\OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED
                )->setErrorCode($e->getCode())
                    ->setResultMessage($message);
            }
        }
    }

    /**
     * @param array $productIds
     * @param string $storeId
     */
    abstract public function doOperation(array $productIds, string $storeId);
}
