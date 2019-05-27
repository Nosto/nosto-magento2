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

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote\Item;
use Nosto\Tagging\Model\Item\Simple as SimpleItem;

class Simple extends SimpleItem
{
    /**
     * Returns the name of the product. Simple products will have their own name
     *
     * @param Item $item the ordered item
     * @return string the name of the product

     */
    public static function buildItemName(Item $item)
    {
        $name = $item->getName();
        $optNames = [];
        $type = $item->getProduct()->getTypeInstance();
        $parentIds = $type->getParentIdsByChild($item->getItemId());
        $objectManager = ObjectManager::getInstance();
        // If the product has a configurable parent, we assume we should tag
        // the parent. If there are many parent IDs, we are safer to tag the
        // products own name alone.
        if (count($parentIds) === 1) {
            try {
                $attributes = $item->getBuyRequest()
                    ->getData('super_attribute');
                if (is_array($attributes)) {
                    foreach ($attributes as $id => $value) {
                        /** @var Attribute $attribute */
                        $attribute = $objectManager->get(Attribute::class)->load($id); // @codingStandardsIgnoreLine
                        $label = $attribute->getSource()->getOptionText($value);
                        if (!empty($label)) {
                            $optNames[] = $label;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // If the item name building fails, it's not crucial
                // No need to handle the exception in any specific way
                unset($e);
            }
        }

        if (!empty($optNames)) {
            $name .= ' (' . implode(', ', $optNames) . ')';
        }
        return $name;
    }
}
