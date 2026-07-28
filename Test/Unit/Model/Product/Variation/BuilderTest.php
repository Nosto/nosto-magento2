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

namespace Nosto\Tagging\Test\Unit\Model\Product\Variation;

use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Simple as SimpleType;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Customer\Model\Data\Group;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\Product\Variation\Builder;
use Nosto\Tagging\Model\ResourceModel\Sku as SkuResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class BuilderTest extends TestCase
{
    /** @var Builder|MockObject */
    private Builder $builder;

    /** @var NostoProductRepository|MockObject */
    private MockObject $nostoProductRepositoryMock;

    /** @var SkuResource|MockObject */
    private MockObject $skuResourceMock;

    /** @var RuleResourceModel|MockObject */
    private MockObject $ruleResourceModelMock;

    /** @var TimezoneInterface|MockObject */
    private MockObject $localeDateMock;

    /** @var Product|MockObject */
    private MockObject $productMock;

    /** @var Store|MockObject */
    private MockObject $storeMock;

    /** @var Group|MockObject */
    private MockObject $groupMock;

    /** @var Website|MockObject */
    private MockObject $websiteMock;

    protected function setUp(): void
    {
        // Builder has a generated factory in its constructor that doesn't exist on disk.
        // Bypass the constructor and inject only the properties that getMinPriceSku() reads.
        $this->builder = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->nostoProductRepositoryMock = $this->createMock(NostoProductRepository::class);
        $this->skuResourceMock = $this->getMockBuilder(SkuResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->ruleResourceModelMock = $this->createMock(RuleResourceModel::class);
        $this->localeDateMock = $this->createMock(TimezoneInterface::class);

        $this->injectProperty('nostoProductRepository', $this->nostoProductRepositoryMock);
        $this->injectProperty('skuResource', $this->skuResourceMock);
        $this->injectProperty('ruleResourceModel', $this->ruleResourceModelMock);
        $this->injectProperty('localeDate', $this->localeDateMock);

        // Align with the production contract: getRulePrice() returns false when no rule applies.
        // An unconfigured mock returns null, which would be misread as a 0.0 rule price.
        $this->ruleResourceModelMock->method('getRulePrice')->willReturn(false);
        $this->localeDateMock->method('scopeDate')->willReturn(new \DateTime());

        $this->websiteMock = $this->createMock(Website::class);

        $this->storeMock = $this->createMock(Store::class);
        $this->storeMock->method('getWebsite')->willReturn($this->websiteMock);
        $this->storeMock->method('getId')->willReturn('1');
        $this->storeMock->method('getWebsiteId')->willReturn('1');

        $this->groupMock = $this->createMock(Group::class);
        $this->groupMock->method('getId')->willReturn('2');

        $this->productMock = $this->createMock(Product::class);
    }

    /**
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuReturnsProductWhenNotConfigurable(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(SimpleType::class));

        $this->nostoProductRepositoryMock->expects($this->never())->method('getSkuIds');

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($this->productMock, $result);
    }

    /**
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuReturnsProductWhenNoSkuIds(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')->willReturn([]);
        $this->skuResourceMock->expects($this->never())->method('getMinPriceSkuId');

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($this->productMock, $result);
    }

    /**
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuReturnsCheapestSku(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20, 30 => 30]);

        $this->skuResourceMock->method('getMinPriceSkuId')
            ->with($this->websiteMock, 2, [10, 20, 30])
            ->willReturn(20);

        $cheapestSku = $this->createMock(Product::class);
        $this->nostoProductRepositoryMock->method('reloadProduct')
            ->with(20, 1)
            ->willReturn($cheapestSku);

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($cheapestSku, $result);
    }

    /**
     * Two different customer groups resolve to the same cheapest SKU on the same store.
     * reloadProduct() is a forced, cache-bypassing load (it exists precisely to avoid stale
     * data across a long-running batch/cron process) — but within a SINGLE product's build,
     * paying that cost twice for the identical SKU+store pair is pure waste. When both calls
     * share the same $reloadedSkuCache array, reloadProduct() must only run once.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuReusesCacheForSameSkuAndStoreAcrossGroups(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);

        $this->skuResourceMock->method('getMinPriceSkuId')
            ->willReturn(20);

        $cheapestSku = $this->createMock(Product::class);
        $this->nostoProductRepositoryMock->expects($this->once())
            ->method('reloadProduct')
            ->with(20, 1)
            ->willReturn($cheapestSku);

        $reloadedSkuCache = [];

        $firstGroup = $this->createMock(Group::class);
        $firstGroup->method('getId')->willReturn('2');
        $secondGroup = $this->createMock(Group::class);
        $secondGroup->method('getId')->willReturn('3');

        $firstResult = $this->builder->getMinPriceSku(
            $this->productMock,
            $firstGroup,
            $this->storeMock,
            $reloadedSkuCache
        );
        $secondResult = $this->builder->getMinPriceSku(
            $this->productMock,
            $secondGroup,
            $this->storeMock,
            $reloadedSkuCache
        );

        $this->assertSame($cheapestSku, $firstResult);
        $this->assertSame($cheapestSku, $secondResult);
    }

    /**
     * Two customer groups resolve to DIFFERENT cheapest SKUs (e.g. group-specific pricing
     * changes which child wins) on the same store. The cache must key by skuId, not just
     * store, so each distinct SKU still gets its own reloadProduct() call and no group's
     * result is silently overwritten by another group's.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuReloadsSeparatelyForDifferentSkusAcrossGroups(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);

        $this->skuResourceMock->method('getMinPriceSkuId')
            ->willReturnOnConsecutiveCalls(10, 20);

        $skuTen = $this->createMock(Product::class);
        $skuTwenty = $this->createMock(Product::class);
        $this->nostoProductRepositoryMock->expects($this->exactly(2))
            ->method('reloadProduct')
            ->willReturnMap([
                [10, 1, $skuTen],
                [20, 1, $skuTwenty],
            ]);

        $reloadedSkuCache = [];

        $firstGroup = $this->createMock(Group::class);
        $firstGroup->method('getId')->willReturn('2');
        $secondGroup = $this->createMock(Group::class);
        $secondGroup->method('getId')->willReturn('3');

        $firstResult = $this->builder->getMinPriceSku(
            $this->productMock,
            $firstGroup,
            $this->storeMock,
            $reloadedSkuCache
        );
        $secondResult = $this->builder->getMinPriceSku(
            $this->productMock,
            $secondGroup,
            $this->storeMock,
            $reloadedSkuCache
        );

        $this->assertSame($skuTen, $firstResult);
        $this->assertSame($skuTwenty, $secondResult);
    }

    /**
     * Index returns null and no in-stock children exist — parent product is returned.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuFallsBackToParentWhenIndexMissingAndNoChildren(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);
        $this->skuResourceMock->method('getMinPriceSkuId')->willReturn(null);
        $this->nostoProductRepositoryMock->method('getInStockSkuProducts')->willReturn([]);

        $this->nostoProductRepositoryMock->expects($this->never())->method('reloadProduct');

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($this->productMock, $result);
    }

    /**
     * Index returns null but in-stock children exist — picks the child with the lowest base price.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuFallbackPicksCheapestChildByBasePrice(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);
        $this->skuResourceMock->method('getMinPriceSkuId')->willReturn(null);

        $expensiveSku = $this->buildChildProductMock(101, 50.0, []);
        $cheapestSku  = $this->buildChildProductMock(102, 30.0, []);

        $this->nostoProductRepositoryMock->method('getInStockSkuProducts')
            ->willReturn([$expensiveSku, $cheapestSku]);

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($cheapestSku, $result);
    }

    /**
     * Fallback picks the child with the lowest effective price after applying a catalog rule.
     * SKU A has base price 50 but a catalog rule brings its effective price to 25.
     * SKU B has base price 40 and no rule. SKU A should win.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuFallbackAppliesCatalogRulePrice(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);
        $this->skuResourceMock->method('getMinPriceSkuId')->willReturn(null);

        $skuA = $this->buildChildProductMock(101, 50.0, []);
        $skuB = $this->buildChildProductMock(102, 40.0, []);

        // Re-inject a fresh mock so the setUp default stub does not stack as a prior
        // invocation and interfere with the per-SKU callback.
        $ruleMock = $this->createMock(RuleResourceModel::class);
        $ruleMock->method('getRulePrice')
            ->willReturnCallback(function ($date, $websiteId, $groupId, $skuId) {
                return $skuId === 101 ? 25.0 : false;
            });
        $this->injectProperty('ruleResourceModel', $ruleMock);

        $this->nostoProductRepositoryMock->method('getInStockSkuProducts')
            ->willReturn([$skuA, $skuB]);

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($skuA, $result);
    }

    /**
     * Fallback applies the tier price for the customer group when it is lower than the base price.
     * SKU A has base price 60 but a group-2 tier price of 20, making its effective price 20.
     * SKU B has base price 35 and no discounts. SKU A should win.
     *
     * @covers Builder::getMinPriceSku()
     */
    public function testGetMinPriceSkuFallbackAppliesTierPriceForGroup(): void
    {
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);
        $this->skuResourceMock->method('getMinPriceSkuId')->willReturn(null);

        $tierPrice = $this->createMock(ProductTierPriceInterface::class);
        $tierPrice->method('getCustomerGroupId')->willReturn('2');
        $tierPrice->method('getValue')->willReturn(20.0);

        $skuA = $this->buildChildProductMock(101, 60.0, [$tierPrice]);
        $skuB = $this->buildChildProductMock(102, 35.0, []);

        $this->nostoProductRepositoryMock->method('getInStockSkuProducts')
            ->willReturn([$skuA, $skuB]);

        $result = $this->builder->getMinPriceSku(
            $this->productMock,
            $this->groupMock,
            $this->storeMock
        );

        $this->assertSame($skuA, $result);
    }

    /**
     * @param int $id
     * @param float $price
     * @param ProductTierPriceInterface[] $tierPrices
     * @return Product|MockObject
     */
    private function buildChildProductMock(int $id, float $price, array $tierPrices): MockObject
    {
        $sku = $this->createMock(Product::class);
        $sku->method('getId')->willReturn($id);
        $sku->method('getPrice')->willReturn($price);
        $sku->method('getTierPrices')->willReturn($tierPrices);
        return $sku;
    }

    private function injectProperty(string $name, object $value): void
    {
        $property = new ReflectionProperty(Builder::class, $name);
        $property->setAccessible(true);
        $property->setValue($this->builder, $value);
    }
}
