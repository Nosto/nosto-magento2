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
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection as AttributeCollection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Model\Config;
use Nosto\Tagging\Logger\Logger;

class DefaultAttributeProvider implements AttributeProviderInterface
{
    /** @var AttributeCollectionFactory */
    private $attributeCollectionFactory;

    /** @var Config */
    private $eavConfig;

    /** @var Logger */
    private $logger;

    /**
     * AttributeProvider constructor.
     * @param AttributeCollectionFactory $attributeCollectionFactory
     * @param Config $eavConfig
     * @param Logger $logger
     */
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory,
        Config $eavConfig,
        Logger $logger
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getSelectableAttributesForNosto()
    {
        try {
            $entity = $this->eavConfig->getEntityType(Product::ENTITY);
            /** @var AttributeCollection $collection */
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
            return $collection;
        } catch (\Exception $e) {
            $this->logger->exception($e);
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getAttributesByAttributeCodes(array $attributeCodes)
    {
        $collection = $this->getSelectableAttributesForNosto();
        if ($collection !== null) {
            $collection->addFieldToFilter('attribute_code', $attributeCodes);
        }
        return $collection;
    }
}
