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

use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped as Type;
use Magento\Sales\Model\Order\Item;
use Magento\Catalog\Model\Product;

class Grouped
{
    public static function getType() {
        return Type::TYPE_CODE;
    }

    /**
     * Returns the name of the product. Grouped products will have their parent's name prepended to
     * their name.
     *
     * @param ObjectManagerInterface $objectManager
     * @param Item $item the ordered item
     * @return string the name of the product
     */
    public static function buildItemName(ObjectManagerInterface $objectManager, Item $item)
    {
        $name = $item->getName();
        $config = $item->getProductOptionByCode('super_product_config');
        if (isset($config['product_id'])) {
            /** @var Product $parent */
            $parent = $objectManager->get('Magento\Catalog\Model\Product')->load($config['product_id']);
            $parentName = $parent->getName();
            if (!empty($parentName)) {
                $name = $parentName . ' - ' . $name;
            }
        }

        return $name;
    }
}
