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

use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;

class Simple
{
    public static function getType()
    {
        return Type::TYPE_SIMPLE;
    }

    /**
     * Returns the name of the product. Simple products will have their own name
     *
     * @param ObjectManagerInterface $objectManager
     * @param Item $item the ordered item
     * @return string the name of the product
     */
    public static function buildItemName(ObjectManagerInterface $objectManager, Item $item)
    {
        $name = $item->getName();
        $optNames = array();
        $type = $item->getProduct()->getTypeInstance();
        $parentIds = $type->getParentIdsByChild($item->getItemId());
        // If the product has a configurable parent, we assume we should tag
        // the parent. If there are many parent IDs, we are safer to tag the
        // products own name alone.
        if (count($parentIds) === 1) {
            $attributes = $item->getBuyRequest()->getData('super_attribute');
            if (is_array($attributes)) {
                foreach ($attributes as $id => $value) {
                    /** @var Attribute $attribute */
                    $attribute = $objectManager->get('Magento\Catalog\Model\ResourceModel\Eav\Attribute')
                        ->load($id); // @codingStandardsIgnoreLine
                    $label = $attribute->getSource()->getOptionText($value);
                    if (!empty($label)) {
                        $optNames[] = $label;
                    }
                }
            }
        }

        if (!empty($optNames)) {
            $name .= ' (' . implode(', ', $optNames) . ')';
        }
        return $name;
    }
}
