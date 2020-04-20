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

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Api\Data\ProductUpdateQueueInterface;
use Nosto\Tagging\Api\ProductUpdateQueueRepositoryInterface;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue as QueueResource;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Update\Queue\QueueCollectionFactory;

class QueueRepository implements ProductUpdateQueueRepositoryInterface
{
    /** @var QueueCollectionFactory  */
    private $queueCollectionFactory;

    /** @var QueueResource  */
    private $queueResource;

    /**
     * IndexRepository constructor.
     *
     * @param QueueResource $cacheResource
     * @param QueueCollectionFactory $cacheCollectionFactory
     */
    public function __construct(
        QueueResource $cacheResource,
        QueueCollectionFactory $cacheCollectionFactory
    ) {
        $this->queueResource = $cacheResource;
        $this->queueCollectionFactory = $cacheCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function getTotalCount(Store $store)
    {
        /* @var QueueCollection $collection */
        $collection = $this->queueCollectionFactory->create();
        if ((int)$store->getId() !== 0) {
            $collection->addStoreFilter($store);
        }
        return $collection->getSize();
    }

    /**
     * @param StoreInterface $store
     * @return QueueCollection
     */
    public function getByStore(StoreInterface $store)
    {
        /* @var QueueCollection $collection */
        return $this->queueCollectionFactory->create()
            ->addStoreFilter($store);
    }

    /**
     * @param ProductUpdateQueueInterface $entry
     * @return ProductUpdateQueueInterface|QueueResource
     * @throws AlreadyExistsException
     */
    public function save(ProductUpdateQueueInterface $entry)
    {
        /** @phan-suppress-next-line PhanTypeMismatchArgument */
        return $this->queueResource->save($entry);
    }

    /**
     * @param ProductUpdateQueueInterface $entry
     * @throws \Exception
     */
    public function delete(ProductUpdateQueueInterface $entry)
    {
        /** @phan-suppress-next-line PhanTypeMismatchArgument */
        $this->queueResource->delete($entry);
    }
}
