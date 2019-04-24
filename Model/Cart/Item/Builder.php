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

namespace Nosto\Tagging\Model\Cart\Item;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Nosto\Object\Cart\LineItem;
use Nosto\Tagging\Model\Item\Downloadable;
use Nosto\Tagging\Model\Item\Giftcard;
use Nosto\Tagging\Model\Item\Virtual;
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
     * @param $currencyCode
     * @return LineItem
     * @throws LocalizedException
     */
    public function build(Item $item, $currencyCode)
    {
        $cartItem = new LineItem();
        $cartItem->setPriceCurrencyCode($currencyCode);
        $cartItem->setProductId($this->buildItemId($item));
        $cartItem->setQuantity((int) $item->getQty());
        $cartItem->setSkuId($this->buildSkuId($item));
        $productType = $item->getProductType();
        // Set default name - this will be overwritten below if matching
        // product type is defined
        $cartItem->setName(sprintf(
            'Not defined - unknown product type: %s',
            $productType
        ));
        switch ($productType) {
            case Simple::getType():
            case Virtual::getType():
            case Downloadable::getType():
            case Giftcard::getType():
                $cartItem->setName(Simple::buildItemName($item));
                break;
            case Configurable::getType():
                $cartItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $cartItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $cartItem->setName((new Grouped($this->productRepository))->buildItemName($item));
                break;
        }
        try {
            $cartItem->setPrice($item->getPriceInclTax());
        } catch (Exception $e) {
            $cartItem->setPrice(0);
        }

        $this->eventManager->dispatch('nosto_cart_item_load_after', ['item' => $cartItem, 'magentoItem' => $item]);

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
        }
        if ($item->getProductType() === Type::TYPE_SIMPLE) {
            try {
                $type = $item->getProduct()->getTypeInstance();
                $parentIds = $type->getParentIdsByChild($item->getItemId());
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
        $product = $item->getProduct();
        if ($product instanceof Product) {
            return (string)$product->getId();
        }
        return LineItem::PSEUDO_PRODUCT_ID;
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
            $children = $item->getChildren();
            //An item with bundle product and group product may have more than 1 child.
            //But configurable product item should have max 1 child item.
            //Here we check the size of children, return only if the size is 1
            if (array_key_exists(0, $children)
                && count($children) === 1
                && $children[0] instanceof Item
                && $children[0]->getProduct() instanceof Product
            ) {
                return (string)$children[0]->getProduct()->getId();
            }
        }

        return null;
    }
}
