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

namespace Nosto\Tagging\Model\Cart\Item;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;
use NostoLineItem;
use Psr\Log\LoggerInterface;

class Builder
{
    private $logger;
    private $objectManager;
    private $eventManager;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        ManagerInterface $eventManager
    ) {
        $this->objectManager = $objectManager;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Item $item
     * @param $currencyCode
     * @return NostoLineItem
     */
    public function build(Item $item, $currencyCode)
    {
        $cartItem = new NostoLineItem();
        $cartItem->setPriceCurrencyCode($currencyCode);
        $cartItem->setProductId($this->buildItemId($item));
        $cartItem->setQuantity($item->getQty());
        switch ($item->getProductType()) {
            case Simple::getType():
                $cartItem->setName(Simple::buildItemName($item));
                break;
            case Configurable::getType():
                $cartItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $cartItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $cartItem->setName(Grouped::buildItemName($item));
                break;
        }
        try {
            $cartItem->setPrice($item->getBasePriceInclTax());
        } catch (Exception $e) {
            $cartItem->setPrice(0);
        }

        $this->eventManager->dispatch('nosto_cart_item_load_after', ['item' => $cartItem]);

        return $cartItem;
    }

    /**
     * @param Item $item
     * @return string
     */
    public function buildItemId(Item $item)
    {
        /** @var Item $parentItem */
        $parentItem = $item->getOptionByCode('product_type');
        if ($parentItem !== null) {
            return $parentItem->getProduct()->getSku();
        } elseif ($item->getProductType() === Type::TYPE_SIMPLE) {
            $type = $item->getProduct()->getTypeInstance();
            $parentIds = $type->getParentIdsByChild($item->getItemId());
            $attributes = $item->getBuyRequest()->getData('super_attribute');
            // If the product has a configurable parent, we assume we should tag
            // the parent. If there are many parent IDs, we are safer to tag the
            // products own ID.
            if (count($parentIds) === 1 && !empty($attributes)) {
                return $parentIds[0];
            }
        }

        return (string) $item->getProduct()->getId();
    }
}
