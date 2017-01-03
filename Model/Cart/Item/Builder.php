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

namespace Nosto\Tagging\Model\Cart\Item;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * Event manager
     *
     * @var ManagerInterface
     */
    protected $eventManager;

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
     * @param Store $store
     * @return \NostoOrderPurchasedItem
     */
    public function build(Item $item, Store $store)
    {
        $cartItem = $this->objectManager->create('NostoOrderPurchasedItem', null);

        try {
            $cartItem->setProductId($this->buildItemId($item));
            $cartItem->setQuantity((int)$item->getQty());
            $cartItem->setName($this->buildItemName($item));
            $cartItem->setPrice($item->getBasePriceInclTax());
            $cartItem->setCurrencyCode($store->getBaseCurrencyCode());
            $cartItems[] = $cartItem;
        } catch (\NostoException $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        $this->eventManager->dispatch(
            'nosto_cart_item_load_after',
            ['item' => $cartItem]
        );

        return $cartItem;
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
