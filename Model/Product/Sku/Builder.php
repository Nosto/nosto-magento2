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
use Nosto\Model\Product\Sku as NostoSku;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Product\Attribute\AttributeServiceInterface;
use Nosto\Tagging\Model\Service\Product\AvailabilityService;
use Nosto\Tagging\Model\Service\Product\ImageService;
use Nosto\Tagging\Model\Service\Stock\StockService;
use Nosto\Types\Product\ProductInterface;

use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order\ItemRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;


// @codingStandardsIgnoreLine

class Builder
{
    /** @var NostoDataHelper */
    private NostoDataHelper $nostoDataHelper;

    /** @var NostoPriceHelper */
    private NostoPriceHelper $nostoPriceHelper;

    /** @var NostoLogger */
    private NostoLogger $nostoLogger;

    /** @var ManagerInterface */
    private ManagerInterface $eventManager;

    /** @var CurrencyHelper */
    private CurrencyHelper $nostoCurrencyHelper;

    /** @var AttributeServiceInterface */
    private AttributeServiceInterface $attributeService;

    /** @var AvailabilityService */
    private AvailabilityService $availabilityService;

    /** @var ImageService */
    private ImageService $imageService;

    /** @var StockService */
    private StockService $stockService;

    private $orderRepository;
    private $orderCollectionFactory;
    private $orderItemRepository;
    private $searchCriteriaBuilder;

    /**
     * Builder constructor.
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoPriceHelper $priceHelper
     * @param NostoLogger $nostoLogger
     * @param ManagerInterface $eventManager
     * @param CurrencyHelper $nostoCurrencyHelper
     * @param AttributeServiceInterface $attributeService
     * @param AvailabilityService $availabilityService
     * @param ImageService $imageService
     * @param StockService $stockService
     */
    public function __construct(
        NostoDataHelper $nostoDataHelper,
        NostoPriceHelper $priceHelper,
        NostoLogger $nostoLogger,
        ManagerInterface $eventManager,
        CurrencyHelper $nostoCurrencyHelper,
        AttributeServiceInterface $attributeService,
        AvailabilityService $availabilityService,
        ImageService $imageService,
        StockService $stockService,
        OrderRepository $orderRepository,
        OrderCollectionFactory $orderCollectionFactory,
        ItemRepository $orderItemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoPriceHelper = $priceHelper;
        $this->nostoLogger = $nostoLogger;
        $this->eventManager = $eventManager;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->attributeService = $attributeService;
        $this->availabilityService = $availabilityService;
        $this->imageService = $imageService;
        $this->stockService = $stockService;
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemRepository = $orderItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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
        if (!$this->availabilityService->isAvailableInStore($product, $store)) {
            return null;
        }

        $nostoSku = new NostoSku();
        try {
            $nostoSku->setId($product->getId());
            $nostoSku->setName($product->getName());
            $nostoSku->setAvailability($this->buildSkuAvailability($product, $store));
            $nostoSku->setImageUrl($this->imageService->buildImageUrl($product, $store));
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
            $gtinAttribute = $this->nostoDataHelper->getGtinAttribute($store);
            if (is_string($gtinAttribute) && $product->hasData($gtinAttribute)) {
                $nostoSku->setGtin($product->getData($gtinAttribute));
            }

            if ($this->nostoDataHelper->isCustomFieldsEnabled($store)) {
                foreach ($attributes as $attribute) {
                    try {
                        $code = $attribute->getProductAttribute()->getAttributeCode();
                        $nostoSku->addCustomField(
                            $code,
                            $this->attributeService->getAttributeValueByAttributeCode($product, $code)
                        );
                    } catch (Exception $e) {
                        $this->nostoLogger->exception($e);
                    }
                }
            }
            $nostoSku->addCustomField('returns', $this->getReturnRate($product->getId()));
            $nostoSku->addCustomField('##id is:', $product->getId());
            if ($this->nostoDataHelper->isInventoryTaggingEnabled($store)) {
                $nostoSku->setInventoryLevel($this->stockService->getQuantity($product, $store));
            }
        } catch (Exception $e) {
            $this->nostoLogger->exception($e);
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
            && $this->availabilityService->isInStock($product, $store)
        ) {
            return ProductInterface::IN_STOCK;
        }

        return ProductInterface::OUT_OF_STOCK;
    }

    public function getReturnRate($productId)
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('status', ['in' => ['complete', 'closed']]);

        $orderItems = [];
        foreach ($orderCollection as $order) {
            foreach ($order->getAllVisibleItems() as $item) {
                $orderItems[] = $item->getProductId();
            }
        }

        $totalOrders = count($orderItems);
        $returnedOrders = $this->getReturnedOrders($productId);
        return (string)$returnedOrders;
//        if ($totalOrders > 0) {
//            $returnRate = $returnedOrders / $totalOrders * 100;
//            return $returnRate;
//        }

//        return 0;
    }

    private function getReturnedOrders($productId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('product_id', $productId)
//            ->addFilter('product_id', "1812")
//            ->addFilter('qty_invoiced', 0, '>')
            ->create();

        $returnedOrders = 0;
        $orderItems = $this->orderItemRepository->getList($searchCriteria)->getItems();

        foreach ($orderItems as $item) {
            $returnedOrders += $item->getQtyRefunded();
        }

        return $returnedOrders;
    }
}
