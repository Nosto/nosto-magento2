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
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder;

abstract class AbstractAttributeService implements AttributeServiceInterface
{
    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var NostoLogger */
    private $logger;

    /** @var AttributeProviderInterface */
    private $attributeProvider;

    /**
     * DefaultAttributeService constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoLogger $logger
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoLogger $logger,
        AttributeProviderInterface $attributeProvider
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->logger = $logger;
        $this->attributeProvider = $attributeProvider;
    }

    /**
     * @inheritDoc
     */
    private function getAttributesByArray(Product $product, array $attributes): array
    {
        $attributesAndValues = [];
        foreach ($attributes as $attribute) {
            try {
                $attributeValue = $this->getAttributeValue($product, $attribute);
                if ($attributeValue !== null) {
                    $attributesAndValues[$attribute->getAttributeCode()] = $attributeValue;
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $attributesAndValues;
    }

    /**
     * @inheritDoc
     */
    public function getAttributesForTags(Product $product, StoreInterface $store): array
    {
        // Attributes that should be used in tagging
        $attributes = array_merge(
            $this->getConfiguredAttributesForTags($store),
            $this->getDefaultAttributesForProduct($product)
        );

        return $this->getAttributesByArray($product, $attributes);
    }

    /**
     * Note that this returns the same attributes than getAttributesForTags
     *
     * @inheritDoc
     */
    public function getAttributesForCustomFields(Product $product, StoreInterface $store): array
    {
        return $this->getAttributesForTags($product, $store);
    }

    /**
     * Returns the default (user defined & visible in frontend) attributes for the given product.
     *
     * @param Product $product
     * @return AbstractAttribute[]
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
                    $attributes[$attribute->getAttributeCode()] = $attribute;
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
     * @return AbstractAttribute[]
     */
    private function getConfiguredAttributesForTags(StoreInterface $store): array
    {
        $configuredAttributes = [];
        $attributes = [];
        foreach (Builder::CUSTOMIZED_TAGS as $tag) {
            $tagAttributes = $this->nostoHelperData->getTagAttributes($tag, $store);
            if (!$tagAttributes) {
                continue;
            }
            foreach ($tagAttributes as $productAttribute) {
                $configuredAttributes[] = $productAttribute;
            }
        }
        $configuredAttributes = array_unique($configuredAttributes);
        $attributeCollection = $this->attributeProvider->getAttributesByAttributeCodes($configuredAttributes);
        if ($attributeCollection === null) {
            return [];
        }
        foreach ($attributeCollection as $code => $productAttribute) {
            if (!in_array($code, $configuredAttributes, true)) {
                $attributes[$code] = $productAttribute;
            }
        }
        return $attributes;
    }

    /**
     * @return NostoLogger
     */
    public function getLogger(): NostoLogger
    {
        return $this->logger;
    }
    /**
     * Resolves "textual" product attribute value.
     * If value is an array containing scalar values the array will be imploded
     * using comma as glue.
     *
     * @param Product $product
     * @param AbstractAttribute $attribute
     * @return bool|float|int|string|null
     */
    abstract public function getAttributeValue(Product $product, AbstractAttribute $attribute);

    /**
     * @inheritDoc
     */
    abstract public function getAttributeValueByAttributeCode(Product $product, $attributeCode);
}
