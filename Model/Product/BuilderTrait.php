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

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Helper\Stock as NostoStockHelper;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;
use Nosto\Tagging\Model\Product\Tags\LowStock as LowStockHelper;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Types\Product\ProductInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;

trait BuilderTrait
{
    private $nostoDataHelper;
    private $nostoPriceHelper;
    private $nostoCategoryBuilder;
    private $nostoStockHelper;
    private $categoryRepository;
    /** @var  AttributeSetRepositoryInterface $attributeSetRepository*/
    private $attributeSetRepository;
    private $galleryReadHandler;
    private $eventManager;
    private $logger;
    private $reviewFactory;
    private $urlBuilder;
    private $skuCollection;
    private $nostoCurrencyHelper;
    private $lowStockHelper;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoCategoryBuilder $categoryBuilder
     * @param NostoStockHelper $stockHelper
     * @param NostoSkuCollection $skuCollection
     * @param CategoryRepositoryInterface $categoryRepository
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param ReviewFactory $reviewFactory
     * @param GalleryReadHandler $galleryReadHandler
     * @param NostoUrlBuilder $urlBuilder
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param LowStockHelper $lowStockHelper
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoCategoryBuilder $categoryBuilder,
        NostoStockHelper $stockHelper,
        NostoSkuCollection $skuCollection,
        CategoryRepositoryInterface $categoryRepository,
        AttributeSetRepositoryInterface $attributeSetRepository,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        ReviewFactory $reviewFactory,
        GalleryReadHandler $galleryReadHandler,
        NostoUrlBuilder $urlBuilder,
        CurrencyHelper $nostoCurrencyHelper,
        LowStockHelper $lowStockHelper
    ) {
        $this->nostoDataHelper = $nostoHelperData;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoCategoryBuilder = $categoryBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoStockHelper = $stockHelper;
        $this->reviewFactory = $reviewFactory;
        $this->galleryReadHandler = $galleryReadHandler;
    }

    /**
     * Tag the custom attributes
     *
     * @param Product $product
     * @param Store $store
     */
    public function buildCustomFields(Product $product, Store $store)
    {
        if (!$this->nostoDataHelper->isCustomFieldsEnabled($store)) {
            return null;
        }

        $attributes = [];

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
                            $attributes[$attributeCode] = $attributeValue;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->exception($e);
            }
        }

        return $attributes;
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
