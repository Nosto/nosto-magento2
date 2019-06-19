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

namespace Nosto\Tagging\Test\Integration\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use Nosto\Tagging\Block\Order as NostoOrderBlock;
use Nosto\Tagging\Test\_util\OrderBuilder;
use Nosto\Tagging\Test\Integration\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tests for Order tagging
 *
 * @magentoAppArea frontend
 */
final class OrderTaggingTest extends TestCase
{
    const ORDER_REGISTRY_KEY = 'order';

    /** @var NostoOrderBlock */
    private $orderBlock;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var \Magento\Sales\Block\Order\Items
     */
    private $model;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->orderBlock = $this->getObjectManager()->create(NostoOrderBlock::class);
        $this->checkoutSession = $this->getObjectManager()->create(CheckoutSession::class);
        $this->registry = $this->getObjectManager()->get(\Magento\Framework\Registry::class);
    }

//    /**
//     * @magentoDataFixture newOrderFixture
//     */
//    public function testGetCustomerGroupName()
//    {
//        $layout = $this->getObjectManager()->get(\Magento\Framework\View\LayoutInterface::class);
//        /** @var \Magento\Sales\Block\Adminhtml\Order\View\Info $customerGroupBlock */
//        $customerGroupBlock = $layout->createBlock(
//            \Magento\Sales\Block\Adminhtml\Order\View\Info::class,
//            'info_block' . mt_rand(),
//            ['registry' => $this->putOrderIntoRegistry()]
//        );
//
//        $result = $customerGroupBlock->getCustomerGroupName();
//        $this->assertEquals('NOT LOGGED IN', $result);
//    }

    public static function newOrderFixture()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $billingAddress = $objectManager->create(OrderAddress::class, ['data' => self::getAddresData()]);
        $billingAddress->setAddressType('billing');

        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('checkmo')
            ->setAdditionalInformation('last_trans_id', '11122')
            ->setAdditionalInformation(
                'metadata',
                [
                    'type' => 'free',
                    'fraudulent' => false,
                ]
            );

        /** @var OrderItem $orderItem */
        $orderItem = $objectManager->create(OrderItem::class);
        $orderItem->setProductId($product->getId())
            ->setQtyOrdered(2)
            ->setBasePrice($product->getPrice())
            ->setPrice($product->getPrice())
            ->setRowTotal($product->getPrice())
            ->setProductType('simple')
            ->setName($product->getName());

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->setIncrementId('100000001')
            ->setState(Order::STATE_PROCESSING)
            ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
            ->setSubtotal(100)
            ->setGrandTotal(100)
            ->setBaseSubtotal(100)
            ->setBaseGrandTotal(100)
            ->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@null.com')
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setStoreId($objectManager->get(StoreManagerInterface::class)->getStore()->getId())
            ->addItem($orderItem)
            ->setPayment($payment);

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
        $orderRepository->save($order);

    }

    /**
     * @param array $additionalOrderData
     * @return \Magento\Framework\Registry|\PHPUnit_Framework_MockObject_MockObject
     */
    private function putOrderIntoRegistry(array $additionalOrderData = [])
    {
        $registry = $this->getMockBuilder(\Magento\Framework\Registry::class)->disableOriginalConstructor()->getMock();

        $order = $this->getObjectManager()->get(
            \Magento\Sales\Model\Order::class
        )->load(
            '100000001'
        )->setData(
            array_merge(['customer_group_id' => 0], $additionalOrderData)
        );

        $registry->expects($this->any())->method('registry')->with('current_order')->will($this->returnValue($order));

        return $registry;
    }

    /**
     * Test that we generate the Nosto order tagging correctly
     */
    public function testOrderTaggingForSimpleOrder()
    {

        $order = $this->registerOrder();
//        $this->model = $this->layout->createBlock(\Magento\Sales\Block\Order\Items::class);
//        $this->assertTrue(count($this->model->getItems()) > 0);



//        $order = (new OrderBuilder($this->getObjectManager()))
//            ->defaultOrder()
//            ->build();
//
//        $registry = $this->putOrderIntoRegistry();
//
//        $this->getObjectManager()->get('Magento\Framework\Registry')->registry('current_category');
//
//        $this->setRegistry(self::ORDER_REGISTRY_KEY, $registry);
//        $this->checkoutSession
//            ->setLastOrderId($order->getId())
//            ->setLastRealOrderId($order->getIncrementId());

        $html = self::stripAllWhiteSpace($this->orderBlock->toHtml());

        $this->assertContains('<spanclass="order_number">', $html);
        $this->assertContains('<spanclass="order_number">M2_</span>', $html);

    }

    /**
     * @return array
     */
    public static function getAddresData()
    {
        return [
            'region' => 'Uusimaa',
            'region_id' => '336',
            'postcode' => '00180',
            'lastname' => 'Solutions',
            'firstname' => 'Nosto',
            'street' => 'Bulevardi 21',
            'city' => 'Helsinki',
            'email' => 'devnull@nosto.com',
            'telephone' => '11111111',
            'country_id' => 'FI'
        ];
    }

    /**
     * Register order in session registry
     *
     * @return \Magento\Sales\Model\Order
     */
    private function registerOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getObjectManager()->get(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('000000001');
        $this->setRegistry('current_order', $order);
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderId($order->getIncrementId());
        return $order;
    }

}

