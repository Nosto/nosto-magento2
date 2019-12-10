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
namespace Nosto\Tagging\Model\Service\Product\Attribute;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Store\Api\Data\StoreInterface;

interface AttributeServiceInterface
{
    /**
     * @param Product $product
     * @param StoreInterface $store
     * @return array ['attributeCode1' => 'value1', 'attributeCode2' => 'value2', ...]
     */
    public function getAttributes(Product $product, StoreInterface $store): array;

    /**
     * Returns the default (user defined & visible in frontend) attributes for the given product
     *
     * @param Product $category
     * @return array ['attributeCode1', 'attributeCode2', ...]
     */
    public function getDefaultAttributesForProduct(Product $product): array;

    /**
     * Resolves "textual" product attribute value.
     * If value is an array containing scalar values the array will be imploded
     * using comma as glue.
     *
     * @param Product $product
     * @param Attribute $store
     * @return bool|float|int|null|string
     */
    public function getAttributeValue(Product $product, Attribute $store);

    /**
     * Resolves "textual" product attribute value by attribute code.
     *
     * @param Product $product
     * @param string $attributeCode
     * @return bool|float|int|null|string
     */
    public function getAttributeValueByAttributeCode(Product $product, $attributeCode);
}
