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

namespace Nosto\Tagging\Model\Service\Product\Attribute;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Helper\ArrayHelper;

class DefaultAttributeService extends AbstractAttributeService
{
    /**
     * @inheritDoc
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function getAttributeValue(Product $product, StoreInterface $store, AbstractAttribute $attribute)
    {
        $value = null;
        try {
            $abstractFrontend = $attribute->getFrontend();
            $frontendValue = $abstractFrontend->getValue($product);
            if (is_array($frontendValue) && !empty($frontendValue)
                && ArrayHelper::onlyScalarValues($frontendValue)
            ) {
                $value = implode(',', $frontendValue);
            } elseif (is_scalar($frontendValue)) {
                $value = $frontendValue;
            } elseif ($frontendValue instanceof Phrase) {
                $value = (string)$frontendValue;
            }
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function getAttributeValueByAttributeCode(Product $product, StoreInterface $store, $attributeCode)
    {
        $attributes = $product->getAttributes(); // This result is cached by Magento
        if (isset($attributes[$attributeCode]) && $attributes[$attributeCode] instanceof AbstractAttribute) {
            /** @var AbstractAttribute $attributes [$attributeCode] */
            return $this->getAttributeValue($product, $attributes[$attributeCode]);
        }
        return null;
    }
}
