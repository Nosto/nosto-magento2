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

declare(strict_types=1);

namespace Nosto\Tagging\Test\Unit\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\FilteredProductException;
use Nosto\Model\ModelFilter;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Helper\Ratings;
use Nosto\Tagging\Helper\Variation as NostoVariationHelper;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;
use Nosto\Tagging\Model\Product\Sku\Collection;
use Nosto\Tagging\Model\Product\Tags\LowStock;
use Nosto\Tagging\Model\Product\Url\Builder;
use Nosto\Tagging\Model\Product\Variation\Collection as PriceVariationCollection;
use Nosto\Tagging\Model\Service\Product\Attribute\AttributeServiceInterface;
use Nosto\Tagging\Model\Service\Product\AvailabilityService;
use Nosto\Tagging\Model\Service\Product\Category\CategoryServiceInterface;
use Nosto\Tagging\Model\Service\Product\ImageService;
use Nosto\Tagging\Model\Service\Stock\StockService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Directory\Model\Currency as MagentoCurrency;

class BuilderTest extends TestCase
{
    /** @var ProductBuilder $builder */
    protected ProductBuilder $builder;
    /** @var Product|(Product&MockObject)|MockObject */
    protected Product $product;
    /** @var Store|(Store&MockObject)|MockObject */
    protected Store $store;

    public function setUp(): void
    {
        $magentoCurrency = $this->createMock(MagentoCurrency::class);
        $magentoCurrency->method('getCode')->willReturn('EUR');
        $currencyHelper = $this->createMock(CurrencyHelper::class);
        $currencyHelper->method('getTaggingCurrency')->willReturn($magentoCurrency);

        $this->builder = new ProductBuilder(
            $this->createMock(NostoDataHelper::class),
            $this->createMock(NostoPriceHelper::class),
            $this->createMock(CategoryServiceInterface::class),
            $this->createMock(Collection::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(ReadHandler::class),
            $this->createMock(Builder::class),
            $currencyHelper,
            $this->createMock(LowStock::class),
            $this->createMock(PriceVariationCollection::class),
            $this->createMock(NostoVariationHelper::class),
            $this->createMock(Ratings::class),
            $this->createMock(AttributeServiceInterface::class),
            $this->createMock(AvailabilityService::class),
            $this->createMock(ImageService::class),
            $this->createMock(StockService::class)
        );

        $this->product = $this->createMock(Product::class);
        $this->store = $this->createMock(Store::class);
    }

    /**
     * @covers ProductBuilder::build()
     * @return void
     */
    public function testCanBuildProduct(): void
    {
        $nostoProduct = $this->builder->build($this->product, $this->store);
        $this->assertInstanceOf(NostoProduct::class, $nostoProduct);
    }

    /**
     * @covers ProductBuilder::build()
     * @return void
     */
    public function testCanBuildProductWithFakeData(): void
    {
        $nostoProduct = $this->builder->build(
            $this->getMagentoProductWithAllFieldsPopulated(),
            $this->store
        );
        $this->assertInstanceOf(NostoProduct::class, $nostoProduct);
    }


    private function getMagentoProductWithAllFieldsPopulated(): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('test');
        $product->method('getSku')->willReturn('test');
        $product->method('getPrice')->willReturn(1.0);
        $product->method('getCreatedAt')->willReturn('2023-01-01 00:00:00');
        $product->method('getUpdatedAt')->willReturn('2023-01-01 00:00:01');
        $product->method('getCategoryIds')->willReturn([1, 2, 3]);
        $product->method('getVisibility')->willReturn(1);
        $product->method('getStatus')->willReturn(1);
        $product->method('getWeight')->willReturn(1.0);
        $product->method('getSpecialPrice')->willReturn(1.0);
        $product->method('getSpecialFromDate')->willReturn('test');
        $product->method('getSpecialToDate')->willReturn('test');
        $product->method('getQty')->willReturn(1.0);

        return $product;
    }

    /**
     * @covers ProductBuilder::build()
     * @return void
     */
    public function testDispatchProductBuildEvents(): void
    {
        $magentoCurrency = $this->createMock(MagentoCurrency::class);
        $magentoCurrency->method('getCode')->willReturn('EUR');
        $currencyHelper = $this->createMock(CurrencyHelper::class);
        $currencyHelper->method('getTaggingCurrency')->willReturn($magentoCurrency);

        $product = $this->createMock(Product::class);
        $store = $this->createMock(Store::class);
//        $modelFilterMock = $this->createMock(ModelFilter::class);
//        $modelFilterMock->method('isValid')->willReturn(false);
        $eventManagerMock = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with(
                'nosto_product_load_before',
                [
                    'product' => new NostoProduct(),
                    'magentoProduct' => $product,
                    'modelFilter' => new ModelFilter()//$modelFilterMock
                ]
            );
//        $eventManagerMock->expects($this->once())
//            ->method('dispatch')
//            ->with(
//                'nosto_product_load_after',
//                [
//                    'product' => new NostoProduct(),
//                    'magentoProduct' => $product,
//                    'modelFilter' => $modelFilterMock
//                ]
//            );
        $builder = new ProductBuilder(
            $this->createMock(NostoDataHelper::class),
            $this->createMock(NostoPriceHelper::class),
            $this->createMock(CategoryServiceInterface::class),
            $this->createMock(Collection::class),
            $eventManagerMock,
            $this->createMock(ReadHandler::class),
            $this->createMock(Builder::class),
            $currencyHelper,
            $this->createMock(LowStock::class),
            $this->createMock(PriceVariationCollection::class),
            $this->createMock(NostoVariationHelper::class),
            $this->createMock(Ratings::class),
            $this->createMock(AttributeServiceInterface::class),
            $this->createMock(AvailabilityService::class),
            $this->createMock(ImageService::class),
            $this->createMock(StockService::class)
        );
        $builder->build($product, $store);
    }
}
