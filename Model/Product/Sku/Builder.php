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

namespace Nosto\Tagging\Model\Product\Sku;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Attribute\Collection
    as ConfigurableAttributeCollection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Object\Product\Sku as NostoSku;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\BuilderTrait;
use Nosto\Tagging\Model\Service\Product\Attribute\AttributeServiceInterface;
use Nosto\Tagging\Model\Service\Stock\StockService;
use Nosto\Types\Product\ProductInterface;

// @codingStandardsIgnoreLine

class Builder
{
    use BuilderTrait {
        BuilderTrait::__construct as builderTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var NostoPriceHelper */
    private $nostoPriceHelper;

    /** @var ManagerInterface */
    private $eventManager;

    /** @var CurrencyHelper */
    private $nostoCurrencyHelper;

    /**
     * Builder constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoPriceHelper $priceHelper
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param StockService $stockService
     * @param StoreManagerInterface $storeManager
     * @param AttributeServiceInterface $attributeService
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoPriceHelper $priceHelper,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        CurrencyHelper $nostoCurrencyHelper,
        StockService $stockService,
        StoreManagerInterface $storeManager,
        AttributeServiceInterface $attributeService
    ) {
        $this->nostoPriceHelper = $priceHelper;
        $this->eventManager = $eventManager;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->builderTraitConstruct(
            $nostoHelperData,
            $stockService,
            $logger,
            $storeManager,
            $attributeService
        );
    }

    /**
     * @param Product $product
     * @param Store $store
     * @param ConfigurableAttributeCollection $attributes
     * @return NostoSku|null
     * @throws Exception
     */
    public function build(
        Product $product,
        Store $store,
        ConfigurableAttributeCollection $attributes
    ) {
        if (!$this->isAvailableInStore($product, $store)) {
            return null;
        }

        $nostoSku = new NostoSku();
        try {
            $nostoSku->setId($product->getId());
            $nostoSku->setName($product->getName());
            $nostoSku->setAvailability($this->buildSkuAvailability($product, $store));
            $nostoSku->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductFinalDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $nostoSku->setPrice($price);
            $listPrice = $this->nostoCurrencyHelper->convertToTaggingPrice(
                $this->nostoPriceHelper->getProductDisplayPrice(
                    $product,
                    $store
                ),
                $store
            );
            $nostoSku->setListPrice($listPrice);
            $gtinAttribute = $this->getDataHelper()->getGtinAttribute($store);
            if ($product->hasData($gtinAttribute)) {
                $nostoSku->setGtin($product->getData($gtinAttribute));
            }

            if ($this->getDataHelper()->isCustomFieldsEnabled($store)) {
                foreach ($attributes as $attribute) {
                    try {
                        $code = $attribute->getProductAttribute()->getAttributeCode();
                        $nostoSku->addCustomField(
                            $code,
                            $this->attributeService->getAttributeValueByAttributeCode($product, $code)
                        );
                    } catch (Exception $e) {
                        $this->getLogger()->exception($e);
                    }
                }
            }
            if ($this->getDataHelper()->isInventoryTaggingEnabled($store)) {
                $nostoSku->setInventoryLevel($this->getStockService()->getQuantity($product, $store));
            }
        } catch (Exception $e) {
            $this->getLogger()->exception($e);
        }

        $this->eventManager->dispatch('nosto_sku_load_after', ['sku' => $nostoSku, 'magentoProduct' => $product]);

        return $nostoSku;
    }

    /**
     * Generates the availability for the SKU
     *
     * @param Product $product
     * @param Store $store
     * @return string
     */
    private function buildSkuAvailability(Product $product, Store $store)
    {
        if ($product->isAvailable()
            && $this->isInStock($product, $store)
        ) {
            return ProductInterface::IN_STOCK;
        }

        return ProductInterface::OUT_OF_STOCK;
    }
}
