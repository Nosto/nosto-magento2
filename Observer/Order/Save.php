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

namespace Nosto\Tagging\Observer\Order;

use DateTime;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as MagentoCustomerRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Nosto\Model\Order\Buyer;
use Nosto\Model\Order\Order as NostoOrder;
use Nosto\Operation\Order\OrderCreate as NostoOrderCreate;
use Nosto\Operation\Order\OrderStatus as NostoOrderUpdate;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Customer\Repository as CustomerRepository;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;
use Nosto\Tagging\Model\Order\Status\Builder as NostoOrderStatusBuilder;
use Nosto\Types\Signup\AccountInterface;

class Save implements ObserverInterface
{
    private NostoHelperData $nostoHelperData;
    private NostoHelperAccount $nostoHelperAccount;
    private NostoLogger $logger;
    private NostoOrderBuilder $nostoOrderBuilder;
    private ModuleManager $moduleManager;
    private CustomerRepository $customerRepository;
    private IndexerInterface $indexer;
    private NostoHelperUrl $nostoHelperUrl;
    private MagentoCustomerRepository $magentoCustomerRepository;
    private NostoOrderStatusBuilder $orderStatusBuilder;
    private static array $sent = [];
    private int $intervalForNew;

    /**
     * Save constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     * @param CustomerRepository $customerRepository
     * @param NostoOrderBuilder $orderBuilder
     * @param NostoOrderStatusBuilder $orderStatusBuilder
     * @param IndexerRegistry $indexerRegistry
     * @param NostoHelperUrl $nostoHelperUrl
     * @param MagentoCustomerRepository $magentoCustomerRepository
     * @param int $intervalForNew
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger,
        ModuleManager $moduleManager,
        CustomerRepository $customerRepository,
        NostoOrderBuilder $orderBuilder,
        NostoOrderStatusBuilder $orderStatusBuilder,
        IndexerRegistry $indexerRegistry,
        NostoHelperUrl $nostoHelperUrl,
        MagentoCustomerRepository $magentoCustomerRepository,
        int $intervalForNew
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoOrderBuilder = $orderBuilder;
        $this->orderStatusBuilder = $orderStatusBuilder;
        $this->customerRepository = $customerRepository;
        $this->indexer = $indexerRegistry->get(ProductIndexer::INDEXER_ID);
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->magentoCustomerRepository = $magentoCustomerRepository;
        $this->intervalForNew = $intervalForNew;
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
            if (!$order) {
                return;
            }

            //Check if order has been sent once
            if (in_array($order->getId(), self::$sent)) {
                return;
            }
            $store = $order->getStore();
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $store
            );
            if ($nostoAccount !== null) {
                //Check if order is new or updated
                if ($this->isNewOrder($order)) {
                    $this->sendNewOrder($order, $nostoAccount, $store);
                } else {
                    $this->sendOrderStatusUpdated($order, $nostoAccount);
                }
                self::$sent[] = $order->getId();
            }
        }
    }

    /**
     * Detects if the order is new (the first time the order is saved)
     *
     * @param Order $order
     * @return bool
     */
    public function isNewOrder(Order $order)
    {
        try {
            $updated = new DateTime($order->getUpdatedAt());
            $created = new DateTime($order->getCreatedAt());
            $diff = $updated->getTimestamp() - $created->getTimestamp();
            return $order->getState() === Order::STATE_NEW && $diff <= $this->intervalForNew;
        } catch (Exception $e) {
            $this->logger->exception($e);
            return true;
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

                /** @phan-suppress-next-line PhanDeprecatedFunction */
                $this->indexer->reindexList($productIds);
            }
        }
    }

    /**
     * @param Order $order
     * @return string|null
     */
    private function getCustomerReference(Order $order): ?string
    {
        $customerId = $order->getCustomerId();
        if (!isset($customerId)) {
            return null; // In case customer is not logged in
        }
        $nostoCustomerId = null;
        try {
            $magentoCustomer = $this->magentoCustomerRepository->getById($customerId);
            // Get the value of `customer_reference`
            $customerReferenceAttribute = $magentoCustomer->getCustomAttribute(
                NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
            );
            if ($customerReferenceAttribute !== null) {
                $nostoCustomerId = $customerReferenceAttribute->getValue();
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        return $nostoCustomerId;
    }

    /**
     * Send new order to Nosto
     *
     * @param Order $order
     * @param AccountInterface $nostoAccount
     * @param Store $store
     */
    private function sendNewOrder(Order $order, AccountInterface $nostoAccount, Store $store)
    {
        /** @var NostoCustomer $nostoCustomer */
        $nostoCustomer = $this->customerRepository
            ->getOneByQuoteId($order->getQuoteId());
        $nostoCustomerId = null;
        $nostoCustomerIdentifier = NostoOrderCreate::IDENTIFIER_BY_CID;
        if ($nostoCustomer instanceof NostoCustomer) {
            $nostoCustomerId = $nostoCustomer->getNostoId();
        }
        // If the id is still null, fetch the `customer_reference`
        if ($nostoCustomerId === null &&
            $this->nostoHelperData->isMultiChannelOrderTrackingEnabled($store)
        ) {
            $nostoCustomerId = $this->getCustomerReference($order);
            $nostoCustomerIdentifier = NostoOrderCreate::IDENTIFIER_BY_REF;
        }
        $nostoOrder = $this->nostoOrderBuilder->build($order);
        if ($nostoCustomerId !== null) {
            try {
                $orderService = new NostoOrderCreate(
                    $nostoOrder,
                    $nostoAccount,
                    $nostoCustomerIdentifier,
                    $nostoCustomerId,
                    $this->nostoHelperUrl->getActiveDomain($store)
                );
                $orderService->execute();
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Failed to save order with quote #%s for customer #%s.
                            Message was: %s',
                        $order->getQuoteId(),
                        (string)$nostoCustomerId,
                        $e->getMessage()
                    )
                );
            }
        } else {
            $this->logger->warning(
                sprintf(
                    'Could not resolve Nosto customer id for order #%s',
                    $order->getQuoteId()
                )
            );
        }
        $this->handleInventoryLevelUpdate($nostoOrder);
    }

    /**
     * Send updated order status to Nosto
     *
     * @param Order $order
     * @param AccountInterface $nostoAccount
     */
    private function sendOrderStatusUpdated(Order $order, AccountInterface $nostoAccount)
    {
        try {
            $orderStatus = $this->orderStatusBuilder->build($order);
            $orderService = new NostoOrderUpdate($nostoAccount, $orderStatus);
            $orderService->execute();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    'Failed to update order with quote #%s.
                        Message was: %s',
                    $order->getQuoteId(),
                    $e->getMessage()
                )
            );
        }
    }
}
