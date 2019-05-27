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

namespace Nosto\Tagging\Model\Config\Source;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Option\ArrayInterface;

/**
 * Abstract option array class to generate a list of selectable options that allows the merchant to
 * choose an attribute for for the specified tagging fields requirements.
 *
 * @package Nosto\Tagging\Model\Config\Source
 */
abstract class Selector implements ArrayInterface
{
    private $attributeCollectionFactory;
    private $eavConfig;

    /**
     * Image constructor.
     * @param AttributeCollectionFactory $attributeCollectionFactory
     * @param Config $eavConfig
     */
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory,
        Config $eavConfig
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Returns all available product attributes
     *
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $entity = $this->eavConfig->getEntityType(Product::ENTITY);
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->attributeCollectionFactory->create();
        $collection->setEntityTypeFilter($entity->getId());
        $collection->addFieldToFilter('attribute_code', [
            'nin' => [
                'name',
                'category_ids',
                'has_options',
                'image_label',
                'old_id',
                'url_key',
                'url_path',
                'small_image_label',
                'thumbnail_label',
                'required_options',
                'tier_price',
                'meta_title',
                'media_gallery',
                'gallery'
            ]
        ]);
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
