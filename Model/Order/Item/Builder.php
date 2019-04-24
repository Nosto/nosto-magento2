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

namespace Nosto\Tagging\Model\Order\Item;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\Order\Item;
use Nosto\Object\Cart\LineItem;
use Magento\Framework\Exception\LocalizedException;
use Nosto\Tagging\Model\Item\Downloadable;
use Nosto\Tagging\Model\Item\Giftcard;
use Nosto\Tagging\Model\Item\Virtual;
use Magento\Catalog\Model\ProductRepository;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    /**
     * @var ManagerInterface $eventManager
     */
    private $eventManager;

    /**
     * @var ProductRepository $productRepository
     */
    private $productRepository;

    /**
     * @var NostoLogger $logger
     */
    private $logger;

    /**
     * Builder constructor.
     *
     * @param ManagerInterface $eventManager
     * @param ProductRepository $productRepository
     * @param NostoLogger $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        ProductRepository $productRepository,
        NostoLogger $logger
    ) {
        $this->eventManager = $eventManager;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * @param Item $item
     * @return LineItem
     * @throws LocalizedException
     */
    public function build(Item $item)
    {
        $order = $item->getOrder();
        $nostoItem = new LineItem();
        $nostoItem->setPriceCurrencyCode($order->getOrderCurrencyCode());
        $nostoItem->setProductId($this->buildItemProductId($item));
        $nostoItem->setQuantity((int)$item->getQtyOrdered());
        $nostoItem->setSkuId($this->buildSkuId($item));
        $productType = $item->getProductType();
        // Set default name - this will be overwritten below if matching
        // product type is defined
        $nostoItem->setName(sprintf(
            'Not defined - unknown product type: %s',
            $productType
        ));
        switch ($productType) {
            case Simple::getType():
            case Virtual::getType():
            case Downloadable::getType():
            case Giftcard::getType():
                $nostoItem->setName(Simple::buildItemName($item));
                break;
            case Configurable::getType():
                $nostoItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $nostoItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $nostoItem->setName((new Grouped($this->productRepository))->buildItemName($item));
                break;
        }
        try {
            $lineDiscount = 0;
            if ($item->getBaseDiscountAmount() > 0) {
                // baseDiscountAmount contains the discount for the whole row
                $lineDiscount = $item->getBaseDiscountAmount() / $item->getQtyOrdered();
            }
            $taxPerUnit = $item->getBaseTaxAmount() / $item->getQtyOrdered();
            $price = $item->getBasePrice() + $taxPerUnit - $lineDiscount;
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

        $this->eventManager->dispatch(
            'nosto_order_item_load_after',
            ['item' => $nostoItem, 'magentoItem' => $item]
        );

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
        }
        if ($item->getProductType() === Type::TYPE_SIMPLE && $item->getProduct() !== null) {
            try {
                $type = $item->getProduct()->getTypeInstance();
                $parentIds = $type->getParentIdsByChild($item->getProductId());
                $attributes = $item->getBuyRequest()->getData('super_attribute');
                // If the product has a configurable parent, we assume we should tag
                // the parent. If there are many parent IDs, we are safer to tag the
                // products own ID.
                if (!empty($attributes) && count($parentIds) === 1) {
                    return $parentIds[0];
                }
            } catch (\Throwable $e) {
                $this->logger->exception($e);
            }
        }
        $productId = $item->getProductId();
        if (!$productId) {
            return LineItem::PSEUDO_PRODUCT_ID;
        }
        return (string)$productId;
    }

    /**
     * Returns the sku id. If it is a configurable product,
     * try to get the child item because the child item is the simple product
     *
     * @param Item $item the sales item model.
     * @return string|null sku id
     */
    public function buildSkuId(Item $item)
    {
        if ($item->getProductType() === Configurable::getType()) {
            $children = $item->getChildrenItems();
            //An item with bundle product and group product may have more than 1 child.
            //But configurable product item should have max 1 child item.
            //Here we check the size of children, return only if the size is 1
            /** @var Item[] $children */
            if (array_key_exists(0, $children)
                && $children[0] instanceof Item
                && count($children) === 1
                && $children[0]->getProductId()
            ) {
                return (string)$children[0]->getProductId();
            }
        }

        return null;
    }
}
