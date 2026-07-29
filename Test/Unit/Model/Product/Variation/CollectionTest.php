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

use Magento\Catalog\Model\Product;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleResourceModel;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Customer\Model\Data\Group;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Price as NostoPriceHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\Product\Variation\Builder;
use Nosto\Tagging\Model\Product\Variation\Collection;
use Nosto\Tagging\Model\ResourceModel\Sku as SkuResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class CollectionTest extends TestCase
{
    private Collection $collection;

    /** @var MockObject */
    private $nostoProductRepositoryMock;

    /** @var MockObject */
    private $skuResourceMock;

    /** @var MockObject */
    private $customerGroupManagerMock;

    /** @var MockObject */
    private $productMock;

    /** @var MockObject */
    private $storeMock;

    /** @var MockObject */
    private $nostoProductMock;

    protected function setUp(): void
    {
        // Builder has a generated factory in its constructor that doesn't exist on disk.
        // Bypass the constructor and inject only the properties Collection::build() exercises.
        $builder = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->nostoProductRepositoryMock = $this->createMock(NostoProductRepository::class);
        $this->skuResourceMock = $this->getMockBuilder(SkuResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        $ruleResourceModelMock = $this->createMock(RuleResourceModel::class);
        $ruleResourceModelMock->method('getRulePrice')->willReturn(false);

        $localeDateMock = $this->createMock(TimezoneInterface::class);
        $localeDateMock->method('scopeDate')->willReturn(new \DateTime());

        $nostoPriceHelperMock = $this->createMock(NostoPriceHelper::class);
        $nostoPriceHelperMock->method('getProductDisplayPrice')->willReturn(10.0);
        $nostoPriceHelperMock->method('getProductPrice')->willReturn(10.0);

        $nostoCurrencyHelperMock = $this->createMock(CurrencyHelper::class);
        $nostoCurrencyHelperMock->method('convertToTaggingPrice')->willReturnArgument(0);

        $eventManagerMock = $this->createMock(ManagerInterface::class);
        $loggerMock = $this->createMock(NostoLogger::class);

        $this->injectProperty($builder, 'nostoProductRepository', $this->nostoProductRepositoryMock);
        $this->injectProperty($builder, 'skuResource', $this->skuResourceMock);
        $this->injectProperty($builder, 'ruleResourceModel', $ruleResourceModelMock);
        $this->injectProperty($builder, 'localeDate', $localeDateMock);
        $this->injectProperty($builder, 'nostoPriceHelper', $nostoPriceHelperMock);
        $this->injectProperty($builder, 'nostoCurrencyHelper', $nostoCurrencyHelperMock);
        $this->injectProperty($builder, 'eventManager', $eventManagerMock);
        $this->injectProperty($builder, 'logger', $loggerMock);

        $this->customerGroupManagerMock = $this->createMock(GroupManagement::class);
        $groupRepositoryMock = $this->createMock(GroupRepository::class);

        $this->collection = new Collection(
            $builder,
            $this->customerGroupManagerMock,
            $groupRepositoryMock,
            $builder
        );

        $websiteMock = $this->createMock(Website::class);

        $this->storeMock = $this->createMock(Store::class);
        $this->storeMock->method('getWebsite')->willReturn($websiteMock);
        $this->storeMock->method('getId')->willReturn('1');
        $this->storeMock->method('getWebsiteId')->willReturn('1');

        $this->productMock = $this->createMock(Product::class);
        $this->productMock->method('getTypeInstance')
            ->willReturn($this->createMock(ConfigurableType::class));

        $this->nostoProductMock = $this->createMock(NostoProduct::class);
        $this->nostoProductMock->method('getVariationId')->willReturn('__default__');
        $this->nostoProductMock->method('getAvailability')->willReturn('InStock');
        $this->nostoProductMock->method('getPriceCurrencyCode')->willReturn('EUR');
    }

    /**
     * Two logged-in customer groups both resolve to the same cheapest SKU on the same
     * store. Collection::build() must share one $reloadedSkuCache across the group loop
     * so Repository::reloadProduct() (a forced, cache-bypassing load) only runs once,
     * even though two Variation objects are still produced — one per group.
     *
     * @covers Collection::build()
     */
    public function testBuildDedupesReloadProductAcrossGroupsForSameSku(): void
    {
        $groupOne = $this->createMock(Group::class);
        $groupOne->method('getId')->willReturn('2');
        $groupOne->method('getCode')->willReturn('general');

        $groupTwo = $this->createMock(Group::class);
        $groupTwo->method('getId')->willReturn('3');
        $groupTwo->method('getCode')->willReturn('wholesale');

        $this->customerGroupManagerMock->method('getLoggedInGroups')
            ->willReturn([$groupOne, $groupTwo]);

        $this->nostoProductRepositoryMock->method('getSkuIds')
            ->willReturn([10 => 10, 20 => 20]);
        $this->skuResourceMock->method('getMinPriceSkuId')->willReturn(20);

        $cheapestSku = $this->createMock(Product::class);
        $cheapestSku->method('getTierPrices')->willReturn([]);

        $this->nostoProductRepositoryMock->expects($this->once())
            ->method('reloadProduct')
            ->with(20, 1)
            ->willReturn($cheapestSku);

        $result = $this->collection->build($this->productMock, $this->nostoProductMock, $this->storeMock);

        $this->assertCount(2, $result);
    }

    private function injectProperty(object $target, string $name, object $value): void
    {
        // PHPUnit mocks are dynamically generated subclasses (e.g. Mock_Builder_xxx).
        // ReflectionProperty cannot resolve a *private* property through a subclass
        // name - only through the exact class that declares it - so walk up the
        // hierarchy to find the declaring class first.
        $class = get_class($target);
        while ($class !== false && !property_exists($class, $name)) {
            $class = get_parent_class($class);
        }
        $property = new ReflectionProperty($class ?: get_class($target), $name);
        $property->setAccessible(true);
        $property->setValue($target, $value);
    }
}
