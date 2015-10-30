<?php

namespace Nosto\Tagging\Model\Cart;

use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Helper\Price as PriceHelper;
use Nosto\Tagging\Model\Cart\Item\Factory as CartItemFactory;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var Factory
     */
    protected $_cartFactory;

    /**
     * @var CartItemFactory
     */
    protected $_cartItemFactory;

    /**
     * @var DataHelper
     */
    protected $_dataHelper;

    /**
     * @var PriceHelper
     */
    protected $_priceHelper;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor.
     *
     * @param Factory         $cartFactory
     * @param CartItemFactory $cartItemFactory
     * @param DataHelper      $dataHelper
     * @param PriceHelper     $priceHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Factory $cartFactory,
        CartItemFactory $cartItemFactory,
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        LoggerInterface $logger
    ) {
        $this->_cartFactory = $cartFactory;
        $this->_cartItemFactory = $cartItemFactory;
        $this->_dataHelper = $dataHelper;
        $this->_priceHelper = $priceHelper;
        $this->_logger = $logger;
    }

    /**
     * @param array $items
     * @param Store $store
     * @return \NostoCart
     */
    public function build(array $items, Store $store)
    {
        $nostoCart = $this->_cartFactory->create();

        try {
            $nostoCart->setItems($this->buildItems($items, $store));
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        var_dump($items);

        return $nostoCart;
    }

    /**
     * @param Item[] $items
     * @param Store  $store
     * @return \NostoCartItemInterface[]
     */
    protected function buildItems(array $items, Store $store)
    {
        $cartItems = array();

        foreach ($items as $item) {
            $cartItem = $this->_cartItemFactory->create();

            $cartItem->setItemId($this->buildItemId($item));
            $cartItem->setQuantity((int)$item->getQty());
            $cartItem->setName($this->buildItemName($item));
            $cartItem->setUnitPrice(
                new \NostoPrice($item->getBasePriceInclTax())
            );
            $cartItem->setCurrency(
                new \NostoCurrencyCode($store->getBaseCurrencyCode())
            );

            $cartItems[] = $cartItem;
        }

        return $cartItems;
    }

    /**
     * @param Item $item
     * @return string
     */
    protected function buildItemId(Item $item)
    {
        /** @var Item $parentItem */
        $parentItem = $item->getOptionByCode('product_type');
        if (!is_null($parentItem)) {
            return $parentItem->getProduct()->getSku();
        } elseif ($item->getProductType() === 'simple') {
            // todo: if the product has a configurable parent and there is "super_attribute" data in the buy request, assume we need to use the parent product SKU, just like in Magento 1.
        }

        return $item->getProduct()->getSku();
    }

    /**
     * @param Item $item
     * @return string
     */
    protected function buildItemName(Item $item)
    {
        // todo: the name must include the variant properties just like in Magento 1
        return $item->getName();
    }
}
