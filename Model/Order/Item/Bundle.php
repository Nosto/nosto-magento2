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

use Magento\Catalog\Model\Product\Type;
use Magento\Sales\Model\Order\Item;

class Bundle
{
    public static function getType() {
        return Type::TYPE_BUNDLE;
    }

    /**
     * Returns the name of the product. Bundle products will have their chosen child product names
     * added.
     *
     * @param Item $item the ordered item
     * @return string the name of the product
     */
    public static function buildItemName(Item $item)
    {
        $name = $item->getName();
        $optNames = array();
        $opts = $item->getProductOptionByCode('bundle_options');
        if (is_array($opts)) {
            foreach ($opts as $opt) {
                if (isset($opt['value']) && is_array($opt['value'])) {
                    foreach ($opt['value'] as $val) {
                        $qty = '';
                        if (isset($val['qty']) && is_int($val['qty'])) {
                            $qty .= $val['qty'] . ' x ';
                        }
                        if (isset($val['title']) && is_string($val['title'])) {
                            $optNames[] = $qty . $val['title'];
                        }
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
