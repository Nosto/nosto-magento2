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

namespace Nosto\Tagging\Model\Cart;

use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Helper\Price as PriceHelper;
use Nosto\Tagging\Model\Cart\Item\Factory as CartItemFactory;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Helper\Item as NostoItemHelper;

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
     * @var NostoItemHelper
     */
    protected $_nostoItemHelper;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor.
     *
     * @param Factory $cartFactory
     * @param CartItemFactory $cartItemFactory
     * @param DataHelper $dataHelper
     * @param PriceHelper $priceHelper
     * @param NostoItemHelper $nostoItemHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Factory $cartFactory,
        CartItemFactory $cartItemFactory,
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        NostoItemHelper $nostoItemHelper,
        LoggerInterface $logger
    ) {
        $this->_cartFactory = $cartFactory;
        $this->_cartItemFactory = $cartItemFactory;
        $this->_dataHelper = $dataHelper;
        $this->_priceHelper = $priceHelper;
        $this->_logger = $logger;
        $this->_nostoItemHelper = $nostoItemHelper;
    }

    /**
     * @param array $items
     * @param Store $store
     * @return \Nosto\Sdk\NostoCart
     */
    public function build(array $items, Store $store)
    {
        $nostoCart = $this->_cartFactory->create();

        try {
            $nostoCart->setItems($this->buildItems($items, $store));
        } catch (\Nosto\Sdk\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $nostoCart;
    }

    /**
     * @param Item[] $items
     * @param Store $store
     * @return \Nosto\Sdk\NostoCartItemInterface[]
     */
    protected function buildItems(array $items, Store $store)
    {
        $cartItems = array();

        foreach ($items as $item) {
            try {
                $cartItem = $this->_cartItemFactory->create();
                $cartItem->setItemId($this->buildItemId($item));
                $cartItem->setQuantity((int)$item->getQty());
                $cartItem->setName($this->buildItemName($item));
                $cartItem->setUnitPrice(
                    new \Nosto\Sdk\NostoPrice($item->getBasePriceInclTax())
                );
                $cartItem->setCurrency(
                    new \Nosto\Sdk\NostoCurrencyCode($store->getBaseCurrencyCode())
                );
                $cartItems[] = $cartItem;
            } catch (\Nosto\Sdk\NostoException $e) {

            }
        }

        return $cartItems;
    }

    /**
     * @param Item $item
     * @return string
     */
    protected function buildItemId(Item $item)
    {

        return $this->_nostoItemHelper->buildProductId($item);
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
