<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Nosto\Model\Category\Category;
use Nosto\Model\Product\Product;
use Nosto\Model\Order\Order;

class MonitoringIndexer extends Template
{
    private static Product $nostoProduct;

    private static Order $nostoOrder;

    private static Category $nostoCategory;

    private static string $entityType;

    private static string $entityId;

    /**
     * Get sync form action
     *
     * @param string $url
     * @return string
     */
    public function getFormAction(string $url): string
    {
        return $this->getUrl($url, ['_secure' => true]);
    }

    /**
     * Set Nosto product in block
     *
     * @param Product $nostoProduct
     * @return void
     */
    public function setNostoProduct(Product $nostoProduct): void
    {
        self::$nostoProduct = $nostoProduct;
    }

    /**
     * Get Nosto product in block
     *
     * @return Product
     */
    public function getNostoProduct(): Product
    {
        return self::$nostoProduct;
    }

    /**
     * Set Nosto order in block
     *
     * @param Order $nostoOrder
     * @return void
     */
    public function setNostoOrder(Order $nostoOrder): void
    {
        self::$nostoOrder = $nostoOrder;
    }

    /**
     * Get Nosto order in block
     *
     * @return Order
     */
    public function getNostoOrder(): Order
    {
        return self::$nostoOrder;
    }

    /**
     * Set Nosto category in block
     *
     * @param Category $nostoCategory
     * @return void
     */
    public function setNostoCategory(Category $nostoCategory): void
    {
        self::$nostoCategory = $nostoCategory;
    }

    /**
     * Get Nosto category in block
     *
     * @return Category
     */
    public function getNostoCategory(): Category
    {
        return self::$nostoCategory;
    }

    /**
     * Set entity type in block
     *
     * @param string $entityType
     * @return void
     */
    public function setEntityType(string $entityType): void
    {
        self::$entityType = $entityType;
    }

    /**
     * Get entity type in block
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return self::$entityType;
    }

    /**
     * Set entity id in block
     *
     * @param string $entityId
     * @return void
     */
    public function setEntityId(string $entityId): void
    {
        self::$entityId = $entityId;
    }

    /**
     * Get entity id in block
     *
     * @return string
     */
    public function getEntityId(): string
    {
        return self::$entityId;
    }
}
