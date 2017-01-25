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

namespace Nosto\Tagging\Model\Order\Item;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;
use NostoLineItem;
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
     * @param $currencyCode
     * @return NostoLineItem
     */
    public function build(Item $item, $currencyCode)
    {
        $nostoItem = new NostoLineItem();
        $nostoItem->setPriceCurrencyCode($currencyCode);
        $nostoItem->setProductId((int)$this->buildItemProductId($item));
        $nostoItem->setQuantity((int)$item->getQtyOrdered());
        switch ($item->getProductType()) {
            case Simple::getType():
                $nostoItem->setName(Simple::buildItemName($this->objectManager, $item));
                break;
            case Configurable::getType():
                $nostoItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $nostoItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $nostoItem->setName(Grouped::buildItemName($this->objectManager, $item));
                break;
        }
        try {
            $nostoItem->setPrice($item->getPriceInclTax() - $item->getBaseDiscountAmount());
        } catch (Exception $e) {
            $nostoItem->setPrice(0);
        }

        $this->eventManager->dispatch(
            'nosto_order_item_load_after',
            ['item' => $nostoItem]
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
     *
     * @return int
     */
    protected function buildItemProductId(Item $item)
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
        return $item->getProductId();
    }
}
