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

namespace Nosto\Tagging\Observer\Product;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Update\QueueService;

class MassProductAttributeUpdate implements ObserverInterface
{

    /** @var QueueService */
    private $queueService;

    /** @var CollectionBuilder */
    private $productCollectionBuilder;

    /** @var NostoLogger */
    private $logger;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /**
     * MassProductAttributeUpdate constructor.
     * @param QueueService $queueService
     * @param CollectionBuilder $productCollectionBuilder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     */
    public function __construct(
        QueueService $queueService,
        CollectionBuilder $productCollectionBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger
    ) {
        $this->queueService = $queueService;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $ids = $observer->getData('product_ids');

        if (!is_array($ids)) {
            $this->logger->debug("Could not add mass updated products to nosto indexer");
            return;
        }

        $stores = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($stores as $store) {
            $this->indexProductsPerStore($store, $ids);
        }
    }

    /**
     * @param Store $store
     * @param array $ids
     */
    private function indexProductsPerStore(Store $store, array $ids)
    {
        $collection = $this->getCollection($store, $ids);
        try {
            $this->queueService->addCollectionToUpsertQueue(
                $collection,
                $store
            );
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return ProductCollection
     */
    private function getCollection(Store $store, array $ids): ProductCollection
    {
        return $this->productCollectionBuilder->initDefault($store)
            ->withIds($ids)
            ->build();
    }
}
