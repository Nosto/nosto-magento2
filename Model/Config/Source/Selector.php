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

namespace Nosto\Tagging\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Framework\Data\OptionSourceInterface;
use Nosto\Tagging\Model\Service\Product\Attribute\AttributeProviderInterface;

/**
 * Abstract option array class to generate a list of selectable options that allows the merchant to
 * choose an attribute for for the specified tagging fields requirements.
 */
abstract class Selector implements OptionSourceInterface
{
    /** @var AttributeProviderInterface */
    private $attributeProvider;

    /**
     * Selector constructor.
     * @param AttributeProviderInterface $attributeProvider
     */
    public function __construct(
        AttributeProviderInterface $attributeProvider
    ) {
        $this->attributeProvider = $attributeProvider;
    }

    /**
     * Returns all available product attributes
     *
     * @return array
     */
    public function toOptionArray()
    {
        $collection = $this->attributeProvider->getSelectableAttributesForNosto();
        if ($collection === null) {
            return [];
        }
        $this->filterCollection($collection);

        $options = $this->isNullable() ? [['value' => 0, 'label' => 'None']] : [];

        /** @var Attribute $attribute */
        foreach ($collection->load() as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontend()->getLabel(),
            ];
        }

        return $options;
    }

    abstract public function filterCollection(Collection $collection);

    abstract public function isNullable();
}
