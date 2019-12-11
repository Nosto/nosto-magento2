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

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Tests\NamingConvention\true\mixed;
use Nosto\Helper\ArrayHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder;

class DefaultAttributeService implements AttributeServiceInterface
{
    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var NostoLogger */
    private $logger;

    /**
     * DefaultAttributeService constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoLogger $logger
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoLogger $logger
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(Product $product, StoreInterface $store): array
    {
        // All attributes configured in this attribute set
        $configuredAttributes = $product->getAttributes(); // This returns all possible attributes for the product type
        // Attributes that should be used in tagging
        $attributesForTags = $this->getAttributesForTags($store);
        // Default attributes
        $defaultAttributes = $this->getDefaultAttributesForProduct($product);
        $attributes = [];
        /** @var Attribute $attribute */
        foreach ($configuredAttributes as $attributeCode => $attribute) {
            if (!in_array($attributeCode, $attributesForTags, true)
                && !in_array($attributeCode, $defaultAttributes, true)
            ) {
                continue;
            }
            try {
                $attributeValue = $this->getAttributeValue($product, $attribute);
                if ($attributeValue !== null) {
                    $attributes[$attribute->getAttributeCode()] = $attributeValue;
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeValueByAttributeCode(Product $product, $attributeCode)
    {
        $attributes = $product->getAttributes(); // This result is cached by Magento
        if (isset($attributes[$attributeCode]) && $attributes[$attributeCode] instanceof Attribute) {
            /** @var Attribute $attributes[$attributeCode] */
            return $this->getAttributeValue($product, $attributes[$attributeCode]);
        }
    }

    /**
     * Returns the default (user defined & visible in frontend) attributes for the given product
     *
     * @param Product $product
     * @return array ['attributeCode1', 'attributeCode2', ...]
     */
    private function getDefaultAttributesForProduct(Product $product): array
    {
        $configuredAttributes = $product->getAttributes();
        $attributes = [];
        /** @var Attribute $attribute */
        foreach ($configuredAttributes as $attributeCode => $attribute) {
            try {
                if ($attribute->getIsUserDefined()
                    && ($attribute->getIsVisibleOnFront() || $attribute->getIsFilterable())
                ) {
                    $attributes[] = $attribute->getAttributeCode();
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $attributes;
    }

    /**
     * Returns unique selected attributes from all tags
     *
     * @param StoreInterface $store
     * @return array
     */
    private function getAttributesForTags(StoreInterface $store)
    {
        $attributes = [];
        foreach (Builder::CUSTOMIZED_TAGS as $tag) {
            $tagAttributes = $this->nostoHelperData->getTagAttributes($tag, $store);
            if (!$tagAttributes) {
                continue;
            }
            foreach ($tagAttributes as $productAttribute) {
                $attributes[] = $productAttribute;
            }
        }
        if ($attributes) {
            return array_unique($attributes);
        }
        return [];
    }

    /**
     * Resolves "textual" product attribute value.
     * If value is an array containing scalar values the array will be imploded
     * using comma as glue.
     *
     * @param Product $product
     * @param Attribute $attribute
     * @return mixed
     */
    private function getAttributeValue(Product $product, Attribute $attribute)
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
            $this->logger->exception($e);
        }
        return $value;
    }
}
