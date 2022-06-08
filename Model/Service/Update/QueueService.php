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

namespace Nosto\Tagging\Model\Service\Update;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Exception\ParentProductDisabledException;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Queue\QueueBuilder;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Util\PagingIterator;

class QueueService extends AbstractService
{
    /** @var QueueRepository  */
    private QueueRepository $queueRepository;

    /** @var QueueBuilder */
    private QueueBuilder $queueBuilder;

    /** @var NostoProductRepository $nostoProductRepository */
    private NostoProductRepository $nostoProductRepository;

    /** @var int $batchSize */
    private int $batchSize;

    /**
     * QueueService constructor.
     * @param QueueRepository $queueRepository
     * @param QueueBuilder $queueBuilder
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoProductRepository $nostoProductRepository
     * @param int $batchSize
     */
    public function __construct(
        QueueRepository $queueRepository,
        QueueBuilder $queueBuilder,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        NostoAccountHelper $nostoAccountHelper,
        NostoProductRepository $nostoProductRepository,
        int $batchSize
    ) {
        parent::__construct($nostoDataHelper, $nostoAccountHelper, $logger);
        $this->queueRepository = $queueRepository;
        $this->queueBuilder = $queueBuilder;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->batchSize = $batchSize;
    }

    /**
     * Sets the products into the update queue
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws Exception
     */
    public function addCollectionToUpsertQueue(ProductCollection $collection, Store $store)
    {
        if ($this->getAccountHelper()->findAccount($store) === null) {
            $this->logDebugWithStore('No nosto account found for the store', $store);
            return;
        }
        $collection->setPageSize($this->batchSize);
        $iterator = new PagingIterator($collection);
        $this->getLogger()->debugWithSource(
            sprintf(
                'Adding %d products to queue for store %s - batch size is %s, total amount of pages %d',
                $collection->getSize(),
                $store->getCode(),
                $this->batchSize,
                $iterator->getLastPageNumber()
            ),
            ['storeId' => $store->getId()],
            $this
        );
        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $queueEntry = $this->queueBuilder->buildForUpsert(
                $store,
                $this->toParentProductIds($page)
            );
            if (!empty($queueEntry->getProductIds()) && !$this->queueRepository->isEntryDuplicated($queueEntry)) {
                $this->queueRepository->save($queueEntry); // @codingStandardsIgnoreLine
            }
        }
    }

    /**
     * Sets the product ids into the delete queue
     *
     * @param $productIds
     * @param Store $store
     * @throws AlreadyExistsException
     */
    public function addIdsToDeleteQueue($productIds, Store $store)
    {
        if ($this->getAccountHelper()->findAccount($store) === null) {
            $this->logDebugWithStore('No nosto account found for the store', $store);
            return;
        }
        $batchedIds = array_chunk($productIds, $this->batchSize);
        /** @var ProductCollection $page */
        foreach ($batchedIds as $idBatch) {
            $queueEntry = $this->queueBuilder->buildForDeletion(
                $store,
                $idBatch
            );
            if (!empty($queueEntry->getProductIds())) {
                $this->queueRepository->save($queueEntry); // @codingStandardsIgnoreLine
            }
        }
    }

    /**
     * @param ProductCollection $collection
     * @return array
     */
    private function toParentProductIds(ProductCollection $collection)
    {
        $productIds = [];
        /** @var ProductInterface $product */
        foreach ($collection->getItems() as $product) {
            try {
                /** @phan-suppress-next-line PhanTypeMismatchArgument */
                $parents = $this->nostoProductRepository->resolveParentProductIds($product);
            } catch (ParentProductDisabledException $e) {
                $this->getLogger()->debug($e->getMessage());
                continue;
            }
            if (!empty($parents)) {
                foreach ($parents as $id) {
                    $productIds[] = $id;
                }
            } else {
                $productIds[] = $product->getId();
            }
        }
        return array_unique($productIds);
    }
}
