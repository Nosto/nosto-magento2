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
use Magento\Framework\App\ObjectManager;
use Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Nosto\Sdk\NostoCurrencyCode;
use Nosto\Sdk\NostoDate;
use Nosto\Sdk\NostoOrderBuyer;
use Nosto\Sdk\NostoOrderItem;
use Nosto\Sdk\NostoOrderPaymentProvider;
use Nosto\Sdk\NostoOrderStatus;
use Nosto\Sdk\NostoPrice;
use Nosto\Tagging\Helper\Price as PriceHelper;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Helper\Item as NostoItemHelper;

class Builder
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var SalesRuleFactory
     */
    protected $_salesRuleFactory;

    /**
     * @var PriceHelper
     */
    protected $_priceHelper;

    /**
     * @var NostoItemHelper
     */
    protected $_nostoItemHelper;

    /**
     * @param LoggerInterface $logger
     * @param SalesRuleFactory $salesRuleFactory
     * @param PriceHelper $priceHelper
     * @param NostoItemHelper $nostoItemHelper
     * @internal param ObjectManager $objectManager
     */
    public function __construct(
        LoggerInterface $logger,
        SalesRuleFactory $salesRuleFactory,
        PriceHelper $priceHelper,
        NostoItemHelper $nostoItemHelper
    ) {
        $this->_logger = $logger;
        $this->_salesRuleFactory= $salesRuleFactory;
        $this->_priceHelper = $priceHelper;
        $this->_nostoItemHelper = $nostoItemHelper;
    }

    /**
     * Loads the order info from a Magento order model.
     *
     * @param Order $order the order model.
     * @return \Nosto\Sdk\NostoOrder
     */
    public function build(Order $order)
    {
        $nostoOrder = new \Nosto\Sdk\NostoOrder();

        try {
            $nostoCurrency = new NostoCurrencyCode($order->getOrderCurrencyCode());
            $nostoOrder->setOrderNumber($order->getId());
            $nostoOrder->setExternalRef($order->getRealOrderId());
            $nostoOrder->setCreatedDate(new NostoDate(strtotime($order->getCreatedAt())));
            $nostoOrder->setPaymentProvider(new NostoOrderPaymentProvider($order->getPayment()->getMethod()));
            if ($order->getStatus()) {
                $nostoStatus = new NostoOrderStatus();
                $nostoStatus->setCode($order->getStatus());
                $nostoStatus->setLabel($order->getStatusLabel());
                $nostoOrder->setStatus($nostoStatus);
            }
            foreach ($order->getAllStatusHistory() as $item) {
                if ($item->getStatus()) {
                    $nostoStatus = new NostoOrderStatus();
                    $nostoStatus->setCode($item->getStatus());
                    $nostoStatus->setLabel($item->getStatusLabel());
                    $nostoStatus->setCreatedAt(new NostoDate(strtotime($item->getCreatedAt())));
                    $nostoOrder->addHistoryStatus($nostoStatus);
                }
            }

            // Set the buyer information
            $nostoBuyer = new NostoOrderBuyer();
            $nostoBuyer->setFirstName($order->getCustomerFirstname());
            $nostoBuyer->setLastName($order->getCustomerLastname());
            $nostoBuyer->setEmail($order->getCustomerEmail());
            $nostoOrder->setBuyer($nostoBuyer);

            // Add each ordered item as a line item
            /** @var Item $item */
            foreach ($order->getAllVisibleItems() as $item) {
                $nostoItem = new NostoOrderItem();
                $nostoItem->setItemId((int)$this->buildItemProductId($item));
                $nostoItem->setQuantity((int)$item->getQtyOrdered());
                $nostoItem->setName($this->buildItemName($item));
                try {
                    $nostoItem->setUnitPrice(
                        new NostoPrice(
                            $this->_priceHelper->getItemFinalPriceInclTax($item)
                        )
                    );
                } catch (\Nosto\Sdk\NostoInvalidArgumentException $E) {
                    $nostoItem->setUnitPrice(
                        new NostoPrice(0)
                    );
                }
                $nostoItem->setCurrency($nostoCurrency);
                $nostoOrder->addItem($nostoItem);
            }

            // Add discounts as a pseudo line item
            if (($discount = $order->getDiscountAmount()) < 0) {
                $nostoItem = new NostoOrderItem();
                $nostoItem->setItemId(-1);
                $nostoItem->setQuantity(1);
                $nostoItem->setName($this->buildDiscountRuleDescription($order));
                $nostoItem->setUnitPrice(new NostoPrice($discount));
                $nostoItem->setCurrency($nostoCurrency);
                $nostoOrder->addItem($nostoItem);
            }

            // Add shipping and handling as a pseudo line item
            if (($shippingInclTax = $order->getShippingInclTax()) > 0) {
                $nostoItem = new NostoOrderItem();
                $nostoItem->setItemId(-1);
                $nostoItem->setQuantity(1);
                $nostoItem->setName('Shipping and handling');
                $nostoItem->setUnitPrice(new NostoPrice($shippingInclTax));
                $nostoItem->setCurrency($nostoCurrency);
                $nostoOrder->addItem($nostoItem);
            }
        } catch (Exception $e) {
            $this->_logger->error($e, ['exception' => $e]);
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
                    $rule = $this->_salesRuleFactory->create()->load($ruleId);
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
        return $this->_nostoItemHelper->buildProductId($item);
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
                        $attribute = ObjectManager::getInstance()->get('Magento\Catalog\Model\ResourceModel\Eav\Attribute')
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
                $parent = ObjectManager::getInstance()->get('Magento\Catalog\Model\Product')
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
