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

namespace Nosto\Tagging\Model\Order\Item;

use Nosto\Tagging\Model\Item\Bundle as BundleItem;
use Magento\Sales\Model\Order\Item;

class Bundle extends BundleItem
{
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
        $optNames = [];
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
