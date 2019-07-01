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

namespace Nosto\Tagging\Model\Order\Status;

use Exception;
use Nosto\NostoException;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Nosto\Object\Order\Order as NostoOrder;
use Nosto\Object\Order\OrderStatus;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Sales\Model\Order\Payment;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;

class Builder
{
    private $logger;
    private $eventManager;

    public function __construct(
        NostoLogger $logger,
        ManagerInterface $eventManager
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * Loads the order info from a Magento order model.
     *
     * @param Order $order the order model.
     * @return NostoOrder
     */
    public function build(Order $order)
    {
        $nostoOrder = new NostoOrder();
        try {
            $nostoOrder->setOrderNumber(NostoOrderBuilder::ORDER_NUMBER_PREFIX . $order->getId());
            $nostoOrder->setExternalOrderRef($order->getRealOrderId());

            // To avoid adding a field on the SDK, let's set the createdAt with updatedAt value
            $orderUpdatedDate = $this->buildUpdatedDate($order);
            $nostoOrder->setCreatedAt($orderUpdatedDate);

            if ($order->getPayment() instanceof Payment) {
                $nostoOrder->setPaymentProvider($order->getPayment()->getMethod());
            } else {
                throw new NostoException('Order has no payment associated');
            }
            if ($order->getStatus()) {
                $nostoStatus = $this->buildOrderStatus($order);
                $nostoOrder->setOrderStatus($nostoStatus);
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        // @TODO: Check if we really need to fire another event here.
        $this->eventManager->dispatch('nosto_order_status_load_after', ['order' => $nostoOrder, 'magentoOrder' => $order]);

        return $nostoOrder;
    }

    /**
     * @param Order $order
     * @return OrderStatus
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function buildOrderStatus(Order $order)
    {
        $nostoStatus = new OrderStatus();
        $nostoStatus->setCode($order->getStatus());
        $nostoStatus->setDate($order->getUpdatedAt());
        $label = $order->getStatusLabel();
        if ($label instanceof Phrase) {
            $nostoStatus->setLabel($label->getText());
        }
        return $nostoStatus;
    }

    /**
     * @param Order $order
     * @return bool|\DateTime
     */
    private function buildUpdatedDate(Order $order)
    {
        $orderUpdated = $order->getUpdatedAt();
        if (is_string($orderUpdated)) {
            $orderUpdatedDate = \DateTime::createFromFormat('Y-m-d H:i:s', $orderUpdated);
            if ($orderUpdatedDate instanceof \DateTimeInterface) {
                return $orderUpdatedDate;
            }
        }
    }
}
