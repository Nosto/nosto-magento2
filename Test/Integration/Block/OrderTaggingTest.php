<?php /** @noinspection SpellCheckingInspection */

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

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Nosto\Tagging\Block\Order as NostoOrderBlock;
use Nosto\Tagging\Test\_util\OrderBuilder;
use Nosto\Tagging\Test\Integration\TestCase;

/**
 * Tests for Order tagging
 *
 * @magentoAppArea frontend
 */
final class OrderTaggingTest extends TestCase
{
    const ORDER_REGISTRY_KEY = 'current_order';

    /* @var NostoOrderBlock */
    private $orderBlock;

    /* @var CheckoutSession */
    private $checkoutSession;

    /* @var Order */
    private $order;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->orderBlock = $this->getObjectManager()->create(NostoOrderBlock::class);
        $this->checkoutSession = $this->getObjectManager()->create(CheckoutSession::class);
        $this->order = $this->registerOrder((new OrderBuilder($this->getObjectManager()))
            ->defaultOrder()
            ->build());
    }

    /**
     * Test that we generate the Nosto order tagging correctly
     */
    public function testOrderTaggingForSimpleOrder()
    {
        $html = self::stripAllWhiteSpace($this->orderBlock->toHtml());

        $this->assertContains('<spanclass="nosto_purchase_order"', $html);
        $this->assertContains('<spanclass="order_number">M2_'. self::stripAllWhiteSpace($this->order->getId()) .'</span>', $html);
        $this->assertContains('<spanclass="created_at">'. self::stripAllWhiteSpace($this->order->getCreatedAt()) .'</span>', $html);
        $this->assertContains('<spanclass="payment_provider">' . self::stripAllWhiteSpace($this->order->getPayment()->getMethod()) . '</span>', $html);
        $this->assertContains('<spanclass="buyer">', $html);
        $this->assertContains('<spanclass="first_name">' . self::stripAllWhiteSpace($this->order->getCustomerFirstname()) . '</span>', $html);
        $this->assertContains('<spanclass="last_name">' . self::stripAllWhiteSpace($this->order->getCustomerLastname()) . '</span>', $html);
        $this->assertContains('<spanclass="email">' . self::stripAllWhiteSpace($this->order->getCustomerEmail()) . '</span>', $html);
        $this->assertContains('<spanclass="purchased_items">', $html);
        $this->assertContains('<spanclass="product_id">' . self::stripAllWhiteSpace($this->order->getItems()[1]->getProductId()) . '</span>', $html);
        $this->assertContains('<spanclass="quantity">' . (int)$this->order->getItems()[1]->getQtyOrdered() . '</span>', $html);
        $this->assertContains('<spanclass="name">' . self::stripAllWhiteSpace($this->order->getItems()[1]->getName()), $html); // Missing attributes
        $this->assertContains('<spanclass="unit_price">' . sprintf("%.2f", $this->order->getItems()[1]->getPriceInclTax()) . '</span>', $html);
        $this->assertContains('<spanclass="price_currency_code">' . $this->order->getOrderCurrencyCode() . '</span>', $html);
        $this->assertContains('<spanclass="order_status_code">' . self::stripAllWhiteSpace($this->order->getStatus()) . '</span>', $html);
        $this->assertContains('<spanclass="external_order_ref">' . $this->order->getIncrementId() . '</span>', $html);
    }

    /**
     * Register order in session registry and returns the loaded object
     * @param Order $order
     * @return Order
     */
    private function registerOrder(Order $order)
    {
        $this->setRegistry(self::ORDER_REGISTRY_KEY, $order);
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderId($order->getIncrementId());
        return $order;
    }
}

