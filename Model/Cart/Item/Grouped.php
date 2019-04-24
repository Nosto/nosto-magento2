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

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item;
use Nosto\Tagging\Model\Item\Grouped as GroupedItem;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;

class Grouped extends GroupedItem
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * Grouped constructor.
     * @param ProductRepository $productRepository
     */
    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Returns the name of the product. Grouped products will have their parent's name prepended to
     * their name.
     *
     * @param Item $item the ordered item
     * @return string|null the name of the product
     */
    public function buildItemName(Item $item)
    {
        $name = $item->getName();
        try {
            $config = $item->getBuyRequest()->getData('super_product_config');
            $itemParent = $this->getGroupedItemParent($config['product_id']);
            if ($itemParent instanceof Product) {
                $itemParentName = $itemParent->getName();
                if ($itemParentName !== null) {
                    return $itemParentName . ' - ' . $name;
                }
            }
        } catch (\Throwable $e) {
            // If the item name building fails, it's not crucial
            // No need to handle the exception in any specific way
            unset($e);
        }

        return $name;
    }

    /**
     * Query the product id and returns the Product Object
     *
     * @param $productId
     * @return ProductInterface|mixed
     * @throws NoSuchEntityException
     */
    private function getGroupedItemParent($productId)
    {
        return $this->productRepository->getById($productId);
    }
}
