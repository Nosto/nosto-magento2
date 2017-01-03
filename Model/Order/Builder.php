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
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use /** @noinspection PhpUndefinedClassInspection */
    Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
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
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        LoggerInterface $logger,
        /** @noinspection PhpUndefinedClassInspection */
        SalesRuleFactory $salesRuleFactory,
        NostoPriceHelper $priceHelper,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->salesRuleFactory= $salesRuleFactory;
        $this->nostoPriceHelper = $priceHelper;
        $this->objectManager = $objectManager;
    }

    /**
     * Loads the order info from a Magento order model.
     *
     * @param Order $order the order model.
     * @return \NostoOrder
     */
    public function build(Order $order)
    {
        $nostoOrder = new \NostoOrder();

        try {
            $nostoOrder->setOrderNumber($order->getId());
            $nostoOrder->setExternalOrderRef($order->getRealOrderId());
            $nostoOrder->setCreatedDate($order->getCreatedAt());
            $nostoOrder->setPaymentProvider($order->getPayment()->getMethod());
            if ($order->getStatus()) {
                $nostoStatus = new \NostoOrderStatus();
                $nostoStatus->setCode($order->getStatus());
                $nostoStatus->setLabel($order->getStatusLabel());
                $nostoOrder->addOrderStatus($nostoStatus);
            }
            /** @var Order\Status\History $item */
            foreach ($order->getAllStatusHistory() as $item) {
                if ($item->getStatus()) {
                    $nostoStatus = new \NostoOrderStatus();
                    $nostoStatus->setCode($item->getStatus());
                    $nostoStatus->setLabel($item->getStatusLabel());
                    $nostoOrder->addOrderStatus($nostoStatus);
                }
            }

            // Set the buyer information
            $nostoBuyer = new \NostoOrderBuyer();
            $nostoBuyer->setFirstName($order->getCustomerFirstname());
            $nostoBuyer->setLastName($order->getCustomerLastname());
            $nostoBuyer->setEmail($order->getCustomerEmail());
            $nostoOrder->setBuyerInfo($nostoBuyer);

            // Add each ordered item as a line item
            /** @var Item $item */
            foreach ($order->getAllVisibleItems() as $item) {
                $nostoItem = new \NostoOrderPurchasedItem();
                $nostoItem->setProductId((int)$this->buildItemProductId($item));
                $nostoItem->setQuantity((int)$item->getQtyOrdered());
                $nostoItem->setName($this->buildItemName($item));
                try {
                    $nostoItem->setPrice(
                        $this->nostoPriceHelper->getItemFinalPriceInclTax($item)
                    );
                } catch (Exception $E) {
                    $nostoItem->setPrice(0);
                }
                $nostoItem->setCurrencyCode($order->getOrderCurrencyCode());
                $nostoOrder->addPurchasedItems($nostoItem);
            }

            // Add discounts as a pseudo line item
            if (($discount = $order->getDiscountAmount()) < 0) {
                $nostoItem = new \NostoOrderPurchasedItem();
                $nostoItem->loadSpecialItemData(
                    $this->buildDiscountRuleDescription($order),
                    $discount,
                    $order->getOrderCurrencyCode()
                );
                $nostoOrder->addPurchasedItems($nostoItem);
            }

            // Add shipping and handling as a pseudo line item
            if (($shippingInclTax = $order->getShippingInclTax()) > 0) {
                $nostoItem = new \NostoOrderPurchasedItem();
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
                    /** @noinspection PhpUndefinedMethodInspection */
                    $rule = $this->salesRuleFactory->create()->load($ruleId);
                    /** @noinspection PhpUndefinedMethodInspection */
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

    /**
     * Returns the product id for a quote item.
     * Always try to find the "parent" product ID if the product is a child of
     * another product type. We do this because it is the parent product that
     * we tag on the product page, and the child does not always have it's own
     * product page. This is important because it is the tagged info on the
     * product page that is used to generate recommendations and email content.
     *
     * @param Item $item the sales item model.
     *
     * @return int
     */
    protected function buildItemProductId(Item $item)
    {
        $parent = $item->getProductOptionByCode('super_product_config');
        if (isset($parent['product_id'])) {
            return $parent['product_id'];
        } elseif ($item->getProductType() === Type::TYPE_SIMPLE) {
            $type = $item->getProduct()->getTypeInstance();
            $parentIds = $type->getParentIdsByChild($item->getProductId());
            $attributes = $item->getBuyRequest()->getData('super_attribute');
            // If the product has a configurable parent, we assume we should tag
            // the parent. If there are many parent IDs, we are safer to tag the
            // products own ID.
            if (count($parentIds) === 1 && !empty($attributes)) {
                return $parentIds[0];
            }
        }
        return $item->getProductId();
    }

    /**
     * Returns the name for a sales item.
     * Configurable products will have their chosen options added to their name.
     * Bundle products will have their chosen child product names added.
     * Grouped products will have their parents name prepended.
     * All others will have their own name only.
     *
     * @param Item $item the sales item model.
     *
     * @return string
     */
    protected function buildItemName(Item $item)
    {
        $name = $item->getName();
        $optNames = array();
        if ($item->getProductType() === Type::TYPE_SIMPLE) {
            $type = $item->getProduct()->getTypeInstance();
            $parentIds = $type->getParentIdsByChild($item->getProductId());
            // If the product has a configurable parent, we assume we should tag
            // the parent. If there are many parent IDs, we are safer to tag the
            // products own name alone.
            if (count($parentIds) === 1) {
                $attributes = $item->getBuyRequest()->getData('super_attribute');
                if (is_array($attributes)) {
                    foreach ($attributes as $id => $value) {
                        /** @var Attribute $attribute */
                        $attribute = $this->objectManager->get('Magento\Catalog\Model\ResourceModel\Eav\Attribute')
                            ->load($id);
                        $label = $attribute->getSource()->getOptionText($value);
                        if (!empty($label)) {
                            $optNames[] = $label;
                        }
                    }
                }
            }
        } elseif ($item->getProductType() === Configurable::TYPE_CODE) {
            $opts = $item->getProductOptionByCode('attributes_info');
            if (is_array($opts)) {
                foreach ($opts as $opt) {
                    if (isset($opt['value']) && is_string($opt['value'])) {
                        $optNames[] = $opt['value'];
                    }
                }
            }
        } elseif ($item->getProductType() === Type::TYPE_BUNDLE) {
            $opts = $item->getProductOptionByCode('bundle_options');
            if (is_array($opts)) {
                foreach ($opts as $opt) {
                    if (isset($opt['value']) && is_array($opt['value'])) {
                        foreach ($opt['value'] as $val) {
                            $qty = '';
                            if (isset($val['qty']) && is_int($val['qty'])) {
                                $qty .= $val['qty'] . ' x ';
                            }
                            if (isset($val['title']) && is_string($val['title'])) {
                                $optNames[] = $qty . $val['title'];
                            }
                        }
                    }
                }
            }
        } elseif ($item->getProductType() === Grouped::TYPE_CODE) {
            $config = $item->getProductOptionByCode('super_product_config');
            if (isset($config['product_id'])) {
                /** @var Product $parent */
                $parent = $this->objectManager->get('Magento\Catalog\Model\Product')
                    ->load($config['product_id']);
                $parentName = $parent->getName();
                if (!empty($parentName)) {
                    $name = $parentName . ' - ' . $name;
                }
            }
        }
        if (!empty($optNames)) {
            $name .= ' (' . implode(', ', $optNames) . ')';
        }
        return $name;
    }
}
