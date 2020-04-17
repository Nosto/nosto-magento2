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

namespace Nosto\Tagging\Model\Product\Queue;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Model\Product\Update\Queue as QueueModel;
use Nosto\Tagging\Model\Product\Update\QueueFactory;

/**
 * Class QueueBuilder
 */
class QueueBuilder
{
    /** @var QueueFactory  */
    private $queueFactory;

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /**
     * Builder constructor.
     * @param QueueFactory $queueFactory
     * @param TimezoneInterface $magentoTimeZone
     */
    public function __construct(
        QueueFactory $queueFactory,
        TimezoneInterface $magentoTimeZone
    ) {
        $this->queueFactory = $queueFactory;
        $this->magentoTimeZone = $magentoTimeZone;
    }

    /**
     * @param StoreInterface $store
     * @param array $productIds
     * @return QueueModel
     */
    public function build(
        StoreInterface $store,
        array $productIds
    ) {
        $queueModel = $this->queueFactory->create();
        $queueModel->setProductIds(array_values($productIds));
        $queueModel->setCreatedAt($this->magentoTimeZone->date());
        $queueModel->setStore($store);
        $queueModel->setStatus(ProductUpdateQueueInterface::STATUS_VALUE_NEW);
        $queueModel->setProductIdCount(count($productIds));
        return $queueModel;
    }

    /**
     * @param StoreInterface $store
     * @param array $productIds
     * @return QueueModel
     */
    public function buildForUpsert(
        StoreInterface $store,
        array $productIds
    ) {
        $queueModel = $this->build($store, $productIds);
        $queueModel->setAction(ProductUpdateQueueInterface::ACTION_VALUE_UPSERT);
        return $queueModel;
    }

    /**
     * @param StoreInterface $store
     * @param array $productIds
     * @return QueueModel
     */
    public function buildForDeletion(
        StoreInterface $store,
        array $productIds
    ) {
        $queueModel = $this->build($store, $productIds);
        $queueModel->setAction(ProductUpdateQueueInterface::ACTION_VALUE_DELETE);
        return $queueModel;
    }
}
