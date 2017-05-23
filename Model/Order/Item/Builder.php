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

namespace Nosto\Tagging\Model\Order\Item;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;
use Nosto\Object\Cart\LineItem;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

class Builder
{
    private $nostoHelperSentry;
    private $objectManager;
    private $eventManager;

    /**
     * Constructor.
     *
     * @param NostoHelperSentry $nostoHelperSentry
     * @param ObjectManagerInterface $objectManager
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        NostoHelperSentry $nostoHelperSentry,
        ObjectManagerInterface $objectManager,
        ManagerInterface $eventManager
    ) {
        $this->objectManager = $objectManager;
        $this->eventManager = $eventManager;
        $this->nostoHelperSentry = $nostoHelperSentry;
    }

    /**
     * @param Item $item
     * @return LineItem
     */
    public function build(Item $item)
    {
        $order = $item->getOrder();
        $nostoItem = new LineItem();
        $nostoItem->setPriceCurrencyCode($order->getOrderCurrencyCode());
        $nostoItem->setProductId($this->buildItemProductId($item));
        $nostoItem->setQuantity((int)$item->getQtyOrdered());
        switch ($item->getProductType()) {
            case Simple::getType():
                $nostoItem->setName(Simple::buildItemName($item));
                break;
            case Configurable::getType():
                $nostoItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $nostoItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $nostoItem->setName(Grouped::buildItemName($item));
                break;
        }
        try {
            $price = $item->getBasePrice() + $item->getBaseTaxAmount() - $item->getBaseDiscountAmount();
            // The item prices are always in base currency, convert to order currency if non base currency
            // is used for the order
            if ($order->getBaseCurrencyCode() !== $order->getOrderCurrencyCode()) {
                $baseCurrency = $order->getBaseCurrency();
                $price = $baseCurrency->convert($price, $order->getOrderCurrencyCode());
            }
            $nostoItem->setPrice($price);
        } catch (Exception $e) {
            $nostoItem->setPrice(0);
        }

        $this->eventManager->dispatch('nosto_order_item_load_after', ['item' => $nostoItem]);

        return $nostoItem;
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
     * @return string
     */
    public function buildItemProductId(Item $item)
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
        return (string)$item->getProductId();
    }
}
