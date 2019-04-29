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

namespace Nosto\Tagging\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order;
use Nosto\Operation\OrderConfirm;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Customer\Repository as CustomerRepository;
use Nosto\Tagging\Model\Indexer\Product\Indexer;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;
use Nosto\Object\Order\Order as NostoOrder;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;

/**
 * Class Save
 * @package Nosto\Tagging\Observer
 */
class Save implements ObserverInterface
{
    private $nostoHelperData;
    private $nostoHelperAccount;
    private $logger;
    private $nostoOrderBuilder;
    private $moduleManager;
    private $customerRepository;
    private $nostoHelperScope;
    private $indexer;
    private $nostoHelperUrl;
    private static $sent = [];

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     * @param CustomerRepository $customerRepository
     * @param NostoOrderBuilder $orderBuilder
     * @param IndexerRegistry $indexerRegistry
     * @param NostoHelperUrl $nostoHelperUrl
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $logger,
        ModuleManager $moduleManager,
        /** @noinspection PhpUndefinedClassInspection */
        CustomerRepository $customerRepository,
        NostoOrderBuilder $orderBuilder,
        IndexerRegistry $indexerRegistry,
        NostoHelperUrl $nostoHelperUrl
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoOrderBuilder = $orderBuilder;
        $this->customerRepository = $customerRepository;
        $this->indexer = $indexerRegistry->get(Indexer::INDEXER_ID);
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperUrl = $nostoHelperUrl;
    }

    /**
     * Event handler for the "catalog_product_save_after" and  event.
     * Sends a product update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @suppress PhanDeprecatedFunction
     * @suppress PhanTypeMismatchArgument
     */
    public function execute(Observer $observer)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            HttpRequest::buildUserAgent(
                'Magento',
                $this->nostoHelperData->getPlatformVersion(),
                $this->nostoHelperData->getModuleVersion()
            );

            /* @var Order $order */
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $observer->getOrder();

            //Check if order has been sent once
            if (in_array($order->getId(), self::$sent)) {
                return;
            }
            $store = $order->getStore();
            $nostoOrder = $this->nostoOrderBuilder->build($order);
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $store
            );
            if ($nostoAccount !== null) {
                $quoteId = $order->getQuoteId();
                /** @var NostoCustomer $nostoCustomer */
                $nostoCustomer = $this->customerRepository
                    ->getOneByQuoteId($quoteId);
                $nostoCustomerId = null;
                if ($nostoCustomer instanceof NostoCustomer) {
                    $nostoCustomerId = $nostoCustomer->getNostoId();
                }
                $orderService = new OrderConfirm($nostoAccount, $this->nostoHelperUrl->getActiveDomain($store));
                try {
                    $orderService->send($nostoOrder, $nostoCustomerId);
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf(
                            'Failed to save order with quote #%s for customer #%s.
                        Message was: %s',
                            $quoteId,
                            $nostoCustomer->getNostoId(),
                            $e->getMessage()
                        )
                    );
                }
                $this->handleInventoryLevelUpdate($nostoOrder);
                self::$sent[] = $order->getId();
            }
        }
    }

    /**
     * Handles the inventory level update to Nosto
     *
     * @param NostoOrder $nostoOrder
     */
    private function handleInventoryLevelUpdate(NostoOrder $nostoOrder)
    {
        //update inventory level
        if (!$this->indexer->isScheduled() && $this->nostoHelperData->isInventoryTaggingEnabled()) {
            $items = $nostoOrder->getPurchasedItems();
            if ($items) {
                $productIds = [];
                foreach ($items as $item) {
                    if ($item->getProductId() !== '-1') {
                        $productIds[] = $item->getProductId();
                    }
                }
                $this->indexer->reindexList($productIds);
            }
        }
    }
}
