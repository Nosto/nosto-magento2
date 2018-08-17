<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Phrase;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Util\Url as UrlUtil;

trait BuilderTrait
{
    private $nostoDataHelperTrait;
    private $loggerTrait;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoLogger $logger
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoLogger $logger
    ) {
        $this->nostoDataHelperTrait = $nostoHelperData;
        $this->loggerTrait = $logger;
    }

    /**
     * Tag the custom attributes
     *
     * @param Product $product
     * @param Store $store
     * @return array
     */
    public function buildCustomFields(Product $product, Store $store)
    {
        $customFields = [];

        if (!$this->nostoDataHelperTrait->isCustomFieldsEnabled($store)) {
            return $customFields;
        }

        $attributes = $product->getTypeInstance()->getSetAttributes($product);
        /** @var AbstractAttribute $attribute */
        foreach ($attributes as $attribute) {
            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute\Interceptor $attribute */
            try {
                //tag user defined attributes that are visible or filterable
                if ($attribute->getIsUserDefined()
                    && ($attribute->getIsVisibleOnFront() || $attribute->getIsFilterable())
                ) {
                    $attributeCode = $attribute->getAttributeCode();
                    //if data is null, do not try to get the value
                    //because the label could be "No" even the value is null
                    if ($product->getData($attributeCode) !== null) {
                        $attributeValue = $this->getAttributeValue($product, $attributeCode);
                        if (is_scalar($attributeValue) && $attributeValue !== '' && $attributeValue !== false) {
                            $customFields[$attributeCode] = $attributeValue;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->loggerTrait->exception($e);
            }
        }

        return $customFields;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return string|null
     */
    public function buildImageUrl(Product $product, Store $store)
    {
        $primary = $this->nostoDataHelperTrait->getProductImageVersion($store);
        $secondary = 'image'; // The "base" image.
        $media = $product->getMediaAttributeValues();

        if (isset($media[$primary])) {
            $image = $media[$primary];
        } elseif (isset($media[$secondary])) {
            $image = $media[$secondary];
        }

        if (empty($image)) {
            return null;
        }

        return $this->finalizeImageUrl(
            $product->getMediaConfig()->getMediaUrl($image),
            $store
        );
    }


    /**
     * Resolves "textual" product attribute value
     *
     * @param Product $product
     * @param $attribute
     * @return bool|float|int|null|string
     */
    public function getAttributeValue(Product $product, $attribute)
    {
        $value = null;
        try {
            $attributes = $product->getAttributes();
            if (isset($attributes[$attribute])) {
                $attributeObject = $attributes[$attribute];
                $frontend = $attributeObject->getFrontend();
                $frontendValue = $frontend->getValue($product);
                if (is_array($frontendValue)) {
                    $value = implode(',', $frontendValue);
                } elseif (is_scalar($frontendValue)) {
                    $value = $frontendValue;
                } elseif ($frontendValue instanceof Phrase) {
                    $value = (string)$frontendValue;
                }
            }
        } catch (\Exception $e) {
            $this->loggerTrait->exception($e);
        }

        return $value;
    }

    /**
     * Finalizes product image urls, stips off "pub/" directory if applicable
     *
     * @param string $url
     * @param Store $store
     * @return string
     */
    public function finalizeImageUrl($url, Store $store)
    {
        if ($this->nostoDataHelperTrait->getRemovePubDirectoryFromProductImageUrl($store)) {
            return UrlUtil::removePubFromUrl($url);
        }

        return $url;
    }
}
