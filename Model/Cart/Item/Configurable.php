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

use Magento\ConfigurableProduct\Model\Product\Type\Configurable as Type;
use Magento\Quote\Model\Quote\Item;

class Configurable
{
    public static function getType()
    {
        return Type::TYPE_CODE;
    }

    /**
     * Returns the name of the product. Configurable products will have their chosen options
     * added to their name.
     *
     * @param Item $item the ordered item
     * @return string the name of the product
     */
    public static function buildItemName(Item $item)
    {
        $name = $item->getName();
        $optNames = array();
        $opts = $item->getOptionByCode('attributes_info');
        if (is_array($opts)) {
            foreach ($opts as $opt) {
                if (isset($opt['value']) && is_string($opt['value'])) {
                    $optNames[] = $opt['value'];
                }
            }
        }

        if (!empty($optNames)) {
            $name .= ' (' . implode(', ', $optNames) . ')';
        }
        return $name;
    }
}
