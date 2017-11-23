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

namespace Nosto\Tagging\Model\Product\Sku;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute as ConfigurableAttribute;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Framework\Phrase;
use Nosto\NostoException;
use Nosto\Object\Product\Sku as NostoSku;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private $nostoDataHelper;
    private $nostoPriceHelper;
    private $galleryReadHandler;
    private $eventManager;
    private $logger;
    private $nostoCurrencyHelper;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param GalleryReadHandler $galleryReadHandler
     * @param CurrencyHelper $nostoCurrencyHelper
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        GalleryReadHandler $galleryReadHandler,
        CurrencyHelper $nostoCurrencyHelper
    ) {
        $this->nostoDataHelper = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @param ConfigurableAttribute[] $attributes
     * @return NostoSku
     */
    public function build(Product $product, Store $store, $attributes)
    {
        $nostoSku = new NostoSku();

        try {
            $nostoSku->setId($product->getId());
            $nostoSku->setName($product->getName());
            $nostoSku->setAvailability($product->isAvailable() ? 'InStock' : 'OutOfStock');
            $nostoSku->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalPriceInclTax(
                    $product
                ),
                $store
            );
            $nostoSku->setPrice($price);
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductPriceInclTax(
                    $product
                ),
                $store
            );
            $nostoSku->setListPrice($listPrice);
            $gtinAttribute = $this->nostoDataHelper->getGtinAttribute($store);
            if ($product->hasData($gtinAttribute)) {
                $nostoSku->setGtin($product->getData($gtinAttribute));
            }

            if ($this->nostoDataHelper->isCustomFieldsEnabled()) {
                foreach ($attributes as $attribute) {
                    try {
                        $code = $attribute->getProductAttribute()->getAttributeCode();
                        $nostoSku->addCustomField($code, $product->getAttributeText($code));
                    } catch (NostoException $e) {
                        $this->logger->exception($e);
                    }
                }
                //load user defined attributes from attribute set
                $this->loadCustomFieldsFromConfigurableAttributes($product, $nostoSku, $store);
            }
        } catch (NostoException $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_sku_load_after', ['sku' => $nostoSku, 'magentoProduct' => $product]);

        return $nostoSku;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return string|null
     */
    public function buildImageUrl(Product $product, Store $store)
    {
        $primary = $this->nostoDataHelper->getProductImageVersion($store);
        $secondary = 'image'; // The "base" image.
        $media = $product->getMediaAttributeValues();
        $image = (isset($media[$primary])
            ? $media[$primary]
            : (isset($media[$secondary]) ? $media[$secondary] : null)
        );

        if (empty($image)) {
            return null;
        }

        return $product->getMediaConfig()->getMediaUrl($image);
    }

    /**
     * Tag the custom attributes
     *
     * @param Product $product
     * @param NostoSku $nostoSku
     * @param Store $store
     */
    protected function loadCustomFieldsFromConfigurableAttributes(Product $product, NostoSku $nostoSku, Store $store)
    {
        if (!$this->nostoDataHelper->isCustomFieldsEnabled($store)) {
            return;
        }

        $attributes = $product->getTypeInstance()->getSetAttributes($product);
        /** @var AbstractAttribute $attribute*/
        foreach ($attributes as $attribute) {
            try {
                //tag user defined attributes only
                if ($attribute->getIsUserDefined()) {
                    $attributeCode = $attribute->getAttributeCode();
                    //if data is null, do not try to get the value
                    //because the label could be "No" even the value is null
                    if ($product->getData($attributeCode) !== null) {
                        $attributeValue = $this->getAttributeValue($product, $attributeCode);
                        if (is_scalar($attributeValue) && $attributeValue !== '' && $attributeValue !== false) {
                            $nostoSku->addCustomField($attributeCode, $attributeValue);
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->exception($e);
            }
        }
    }

    /**
     * Resolves "textual" product attribute value
     *
     * @param Product $product
     * @param $attribute
     * @return bool|float|int|null|string
     */
    private function getAttributeValue(Product $product, $attribute)
    {
        $value = null;
        try {
            $attributes = $product->getAttributes();
            if (isset($attributes[$attribute])) {
                $attributeObject = $attributes[$attribute];
                $frontend = $attributeObject->getFrontend();
                $frontendValue = $frontend->getValue($product);
                if (is_array($frontendValue)) {
                    $value = implode(",", $frontendValue);
                } elseif (is_scalar($frontendValue)) {
                    $value = $frontendValue;
                } elseif ($frontendValue instanceof Phrase) {
                    $value = (string)$frontendValue;
                }
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return $value;
    }
}
