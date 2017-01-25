<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Model\Order;

use Exception;
use Nosto\Tagging\Model\Order\Item\Builder as NostoOrderItemBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use NostoLineItem;
use NostoOrder;
use NostoOrderBuyer;
use NostoOrderStatus;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var SalesRuleFactory
     */
    protected $salesRuleFactory;

    /**
     * @var NostoPriceHelper
     */
    protected $nostoPriceHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @param LoggerInterface $logger
     * @param SalesRuleFactory $salesRuleFactory
     * @param NostoPriceHelper $priceHelper
     * @param NostoOrderItemBuilder $nostoOrderItemBuilder
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        LoggerInterface $logger,
        /** @noinspection PhpUndefinedClassInspection */
        SalesRuleFactory $salesRuleFactory,
        NostoPriceHelper $priceHelper,
        NostoOrderItemBuilder $nostoOrderItemBuilder,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->salesRuleFactory = $salesRuleFactory;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoOrderItemBuilder = $nostoOrderItemBuilder;
        $this->objectManager = $objectManager;
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
            $nostoOrder->setOrderNumber($order->getId());
            $nostoOrder->setExternalOrderRef($order->getRealOrderId());
            $nostoOrder->setCreatedDate($order->getCreatedAt());
            $nostoOrder->setPaymentProvider($order->getPayment()->getMethod());
            if ($order->getStatus()) {
                $nostoStatus = new NostoOrderStatus();
                $nostoStatus->setCode($order->getStatus());
                $nostoStatus->setLabel($order->getStatusLabel());
                $nostoOrder->addOrderStatus($nostoStatus);
            }
            /** @var Order\Status\History $item */
            foreach ($order->getAllStatusHistory() as $item) {
                if ($item->getStatus()) {
                    $nostoStatus = new NostoOrderStatus();
                    $nostoStatus->setCode($item->getStatus());
                    $nostoStatus->setLabel($item->getStatusLabel());
                    $nostoOrder->setOrderStatus($nostoStatus);
                }
            }

            // Set the buyer information
            $nostoBuyer = new NostoOrderBuyer();
            $nostoBuyer->setFirstName($order->getCustomerFirstname());
            $nostoBuyer->setLastName($order->getCustomerLastname());
            $nostoBuyer->setEmail($order->getCustomerEmail());
            $nostoOrder->setCustomer($nostoBuyer);

            // Add each ordered item as a line item
            /** @var Item $item */
            foreach ($order->getAllVisibleItems() as $item) {
                $nostoItem = $this->nostoOrderItemBuilder->build($item, $order->getOrderCurrencyCode());
                $nostoOrder->addPurchasedItems($nostoItem);
            }

            // Add discounts as a pseudo line item
            if (($discount = $order->getDiscountAmount()) < 0) {
                $nostoItem = new NostoLineItem();
                $nostoItem->loadSpecialItemData(
                    $this->buildDiscountRuleDescription($order),
                    $discount,
                    $order->getOrderCurrencyCode()
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }

            // Add shipping and handling as a pseudo line item
            if (($shippingInclTax = $order->getShippingInclTax()) > 0) {
                $nostoItem = new NostoLineItem();
                $nostoItem->loadSpecialItemData(
                    'Shipping and handling',
                    $shippingInclTax,
                    $order->getOrderCurrencyCode()
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }

        } catch (Exception $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $nostoOrder;
    }

    /**
     * Generates a textual description of the applied discount rules
     *
     * @param Order $order
     * @return string discount description
     */
    protected function buildDiscountRuleDescription(Order $order)
    {
        try {
            $appliedRules = array();
            foreach ($order->getAllVisibleItems() as $item) {
                /* @var Item $item */
                $itemAppliedRules = $item->getAppliedRuleIds();
                if (empty($itemAppliedRules)) {
                    continue;
                }
                $ruleIds = explode(',', $item->getAppliedRuleIds());
                foreach ($ruleIds as $ruleId) {
                    $rule = $this->salesRuleFactory->create()->load($ruleId); // @codingStandardsIgnoreLine
                    $appliedRules[$ruleId] = $rule->getName();
                }
            }
            if (count($appliedRules) == 0) {
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
