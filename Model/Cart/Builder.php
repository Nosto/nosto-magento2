<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Cart;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Cart\Cart;
use Nosto\Tagging\Model\Cart\Item\Builder as NostoCartItemBuilder;
use NostoCart;
use Psr\Log\LoggerInterface;

class Builder
{
    private $nostoCartItemBuilder;
    private $logger;
    private $objectManager;
    private $eventManager;

    /**
     * Constructor.
     *
     * @param NostoCartItemBuilder $nostoCartItemBuilder
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     * @param ManagerInterface $eventManager
     * @internal param CartItemFactory $cartItemFactory
     */
    public function __construct(
        NostoCartItemBuilder $nostoCartItemBuilder,
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        ManagerInterface $eventManager
    ) {
        $this->objectManager = $objectManager;
        $this->nostoCartItemBuilder = $nostoCartItemBuilder;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Quote $quote
     * @param StoreInterface|Store $store
     * @return Cart
     * @internal param array $items
     */
    public function build(Quote $quote, StoreInterface $store)
    {
        $nostoCart = new Cart();

        foreach ($quote->getAllVisibleItems() as $item) {
            try {
                $cartItem = $this->nostoCartItemBuilder->build($item, $store->getBaseCurrencyCode());
                $nostoCart->addItem($cartItem);
            } catch (\NostoException $e) {
                $this->logger->error($e->__toString());
            }
        }

        $this->eventManager->dispatch('nosto_cart_load_after', ['cart' => $nostoCart]);

        return $nostoCart;
    }
}
