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

declare(strict_types=1);

namespace Nosto\Tagging\Test\Integration\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Nosto\Tagging\Block\Order as NostoOrderBlock;
use Nosto\Tagging\Test\_util\OrderBuilder;
use Nosto\Tagging\Test\Integration\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Magento\Checkout\Model\Session as CheckoutSession;

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
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->orderBlock = $this->getObjectManager()->create(NostoOrderBlock::class);
        $this->checkoutSession = $this->getObjectManager()->create(CheckoutSession::class);
    }



    /**
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testGetCustomerGroupName()
    {
        $layout = $this->getObjectManager()->get(\Magento\Framework\View\LayoutInterface::class);
        /** @var \Magento\Sales\Block\Adminhtml\Order\View\Info $customerGroupBlock */
        $customerGroupBlock = $layout->createBlock(
            \Magento\Sales\Block\Adminhtml\Order\View\Info::class,
            'info_block' . mt_rand(),
            ['registry' => $this->putOrderIntoRegistry()]
        );

        $result = $customerGroupBlock->getCustomerGroupName();
        $this->assertEquals('NOT LOGGED IN', $result);
    }

    /**
     * @param array $additionalOrderData
     * @return \Magento\Framework\Registry|\PHPUnit_Framework_MockObject_MockObject
     */
    private function putOrderIntoRegistry(array $additionalOrderData = [])
    {
        $registry = $this->getMockBuilder(\Magento\Framework\Registry::class)->disableOriginalConstructor()->getMock();

        $order = $this->_objectManager->get(
            \Magento\Sales\Model\Order::class
        )->load(
            '100000001'
        )->setData(
            array_merge(['customer_group_id' => 0], $additionalOrderData)
        );

        $registry->expects($this->any())->method('registry')->with('current_order')->will($this->returnValue($order));

        return $registry;
    }

//    /**
//     * Test that we generate the Nosto order tagging correctly
//     */
//    public function testOrderTaggingForSimpleOrder()
//    {
//
        /* @var OrderRepositoryInterface $orderRepo */
//        $order = (new OrderBuilder($this->getObjectManager()))
//            ->defaultOrder()
//            ->build();

//        $this->setRegistry(self::ORDER_REGISTRY_KEY, $order);

//        $this->checkoutSession
//            ->setLastOrderId($order->getId())
//            ->setLastRealOrderId($order->getIncrementId());

//        $html = self::stripAllWhiteSpace($this->orderBlock->toHtml());
//
//        $this->assertContains('<spanclass="order_number">', $html);
//        $this->assertContains('<spanclass="name">NostoSimpleOrder</span>', $html);
//
//
//
//        $this->assertContains('<span class="first_name">John</span>', $html); // Missing upper buyer span
//
//
//    }


}

