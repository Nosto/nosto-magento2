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

namespace Nosto\Tagging\Model\Order;

use Exception;
use Nosto\NostoException;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Nosto\Object\Cart\LineItem;
use Nosto\Object\Order\Buyer;
use Nosto\Object\Order\Order as NostoOrder;
use Nosto\Object\Order\OrderStatus;
use Nosto\Tagging\Model\Order\Item\Builder as NostoOrderItemBuilder;
use Nosto\Tagging\Model\Order\Buyer\Builder as NostoBuyerBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Sales\Model\Order\Payment;

class Builder
{
    const ORDER_NUMBER_PREFIX = 'M2_';

    private $logger;
    /** @noinspection PhpUndefinedClassInspection */
    private $salesRuleFactory;
    private $nostoOrderItemBuilder;
    private $eventManager;
    private $buyerBuilder;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @param NostoLogger $logger
     * @param SalesRuleFactory $salesRuleFactory
     * @param NostoOrderItemBuilder $nostoOrderItemBuilder
     * @param ManagerInterface $eventManager
     * @param NostoBuyerBuilder $buyerBuilder
     */
    public function __construct(
        NostoLogger $logger,
        /** @noinspection PhpUndefinedClassInspection */
        SalesRuleFactory $salesRuleFactory,
        NostoOrderItemBuilder $nostoOrderItemBuilder,
        ManagerInterface $eventManager,
        NostoBuyerBuilder $buyerBuilder
    ) {
        $this->logger = $logger;
        $this->salesRuleFactory = $salesRuleFactory;
        $this->nostoOrderItemBuilder = $nostoOrderItemBuilder;
        $this->eventManager = $eventManager;
        $this->buyerBuilder = $buyerBuilder;
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
            $nostoOrder->setOrderNumber(self::ORDER_NUMBER_PREFIX . $order->getId());
            $nostoOrder->setExternalOrderRef($order->getRealOrderId());
            $orderCreated = $order->getCreatedAt();
            if (is_string($orderCreated)) {
                $orderCreatedDate = \DateTime::createFromFormat('Y-m-d H:i:s', $orderCreated);
                if ($orderCreatedDate instanceof \DateTimeInterface) {
                    $nostoOrder->setCreatedAt($orderCreatedDate);
                }
            }
            if ($order->getPayment() instanceof Payment) {
                $nostoOrder->setPaymentProvider($order->getPayment()->getMethod());
            } else {
                throw new NostoException('Order has no payment associated');
            }
            if ($order->getStatus()) {
                $nostoStatus = new OrderStatus();
                $nostoStatus->setCode($order->getStatus());
                $nostoStatus->setDate($order->getUpdatedAt());
                $label = $order->getStatusLabel();
                if ($label instanceof Phrase) {
                    $nostoStatus->setLabel($label->getText());
                }
                $nostoOrder->setOrderStatus($nostoStatus);
            }
            $nostoBuyer = $this->buyerBuilder->fromOrder($order);
            if ($nostoBuyer instanceof Buyer) {
                $nostoOrder->setCustomer($nostoBuyer);
            }

            // Add each ordered item as a line item
            /** @var Item $item */
            foreach ($order->getAllVisibleItems() as $item) {
                if ($item->getProduct() instanceof Product) {
                    $nostoItem = $this->nostoOrderItemBuilder->build($item);
                    $nostoOrder->addPurchasedItems($nostoItem);
                }
            }

            // Add discounts as a pseudo line item
            if (($discount = $order->getDiscountAmount()) < 0) {
                $nostoItem = new LineItem();
                if ($order->getBaseCurrencyCode() !== $order->getOrderCurrencyCode()) {
                    $baseCurrency = $order->getBaseCurrency();
                    $discount = $baseCurrency->convert($discount, $order->getOrderCurrencyCode());
                }
                $nostoItem->loadSpecialItemData(
                    $this->buildDiscountRuleDescription($order),
                    $discount === null ? 0 : $discount,
                    $order->getOrderCurrencyCode()
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }
            // Add shipping and handling as a pseudo line item
            if (($shippingInclTax = $order->getShippingInclTax()) > 0) {
                $nostoItem = new LineItem();
                if ($order->getBaseCurrencyCode() !== $order->getOrderCurrencyCode()) {
                    $baseCurrency = $order->getBaseCurrency();
                    $shippingInclTax = $baseCurrency->convert($shippingInclTax, $order->getOrderCurrencyCode());
                }
                $nostoItem->loadSpecialItemData(
                    'Shipping and handling',
                    $shippingInclTax === null ? 0 : $shippingInclTax,
                    $order->getOrderCurrencyCode()
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_order_load_after', ['order' => $nostoOrder, 'magentoOrder' => $order]);

        return $nostoOrder;
    }

    /**
     * Generates a textual description of the applied discount rules
     *
     * @param Order $order
     * @return string discount description
     * @suppress PhanDeprecatedFunction
     */
    public function buildDiscountRuleDescription(Order $order)
    {
        try {
            $appliedRules = [];
            foreach ($order->getAllVisibleItems() as $item) {
                /* @var Item $item */
                $itemAppliedRules = $item->getAppliedRuleIds();
                if ($itemAppliedRules === null) {
                    continue;
                }
                $ruleIds = explode(',', $item->getAppliedRuleIds());
                foreach ($ruleIds as $ruleId) {
                    /** @noinspection PhpDeprecationInspection */
                    $rule = $this->salesRuleFactory->create()->load($ruleId); // @codingStandardsIgnoreLine
                    $appliedRules[$ruleId] = $rule->getName();
                }
            }
            if (count($appliedRules) === 0) {
                $appliedRules[] = 'unknown rule';
            }
            $discountTxt = sprintf(
                'Discount (%s)',
                implode(', ', $appliedRules)
            );
        } catch (\Exception $e) {
            $discountTxt = 'Discount (error)';
        }

        return $discountTxt;
    }
}
