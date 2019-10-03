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
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Nosto\NostoException;
use Nosto\Object\Order\GraphQL\OrderStatus as NostoOrderStatus;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    const ORDER_NUMBER_PREFIX = 'M2_';

    /** @var NostoLogger  */
    private $logger;

    /** @var ManagerInterface  */
    private $eventManager;

    /**
     * Builder constructor.
     * @param ManagerInterface $eventManager
     * @param NostoLogger $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        NostoLogger $logger
    ) {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Order $order
     * @return NostoOrderStatus|null
     */
    public function build(Order $order)
    {
        $orderNumber = self::ORDER_NUMBER_PREFIX.''.$order->getId();
        $orderStatus = $order->getStatus();
        $updatedAt = $order->getUpdatedAt();
        try {
            if ($order->getPayment() instanceof Payment) {
                $paymentProvider = $order->getPayment()->getMethod();
            } else {
                throw new NostoException('Order has no payment associated');
            }

            $nostoOrderStatus = new NostoOrderStatus(
                $orderNumber,
                $orderStatus,
                $paymentProvider,
                $updatedAt
            );

            $this->eventManager->dispatch(
                'nosto_order_status_load_after',
                ['order' => $nostoOrderStatus, 'magentoOrder' => $order]
            );

            return $nostoOrderStatus;
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        return null;
    }
}
