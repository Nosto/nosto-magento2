<?php

/**
 * Copyright (c) 2026, Nosto Solutions Ltd
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
 * @copyright 2026 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

declare(strict_types=1);

namespace Nosto\Tagging\Test\Unit\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use Nosto\Tagging\Plugin\ProductVisibilityUpdate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductVisibilityUpdateTest extends TestCase
{
    /** @var ProductVisibilityUpdate */
    private ProductVisibilityUpdate $plugin;

    /** @var ProductAction|MockObject */
    private MockObject $productActionMock;

    /** @var CollectionBuilder|MockObject */
    private MockObject $productCollectionBuilderMock;

    /** @var ProductCollection|MockObject */
    private MockObject $productCollectionMock;

    /** @var ProductStoreLink|MockObject */
    private MockObject $productStoreLinkMock;

    /** @var NostoHelperScope|MockObject */
    private MockObject $nostoHelperScopeMock;

    /** @var ProductUpdateService|MockObject */
    private MockObject $productUpdateServiceMock;

    protected function setUp(): void
    {
        $this->productActionMock = $this->createMock(ProductAction::class);
        $this->productCollectionMock = $this->createMock(ProductCollection::class);

        $this->productCollectionBuilderMock = $this->createMock(CollectionBuilder::class);
        $this->productCollectionBuilderMock->method('withStore')->willReturnSelf();
        $this->productCollectionBuilderMock->method('withIds')->willReturnSelf();
        $this->productCollectionBuilderMock->method('build')->willReturn($this->productCollectionMock);

        $this->productStoreLinkMock = $this->createMock(ProductStoreLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->productUpdateServiceMock = $this->createMock(ProductUpdateService::class);

        $this->plugin = new ProductVisibilityUpdate(
            $this->productCollectionBuilderMock,
            $this->productStoreLinkMock,
            $this->nostoHelperScopeMock,
            $this->productUpdateServiceMock,
            $this->createMock(NostoLogger::class)
        );
    }

    /**
     * @param int $id
     * @param int $visibility
     * @return Product|MockObject
     */
    private function mockProductItem(int $id, int $visibility): MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getVisibility')->willReturn($visibility);
        return $product;
    }

    /**
     * @param Store[] $stores
     * @return Website|MockObject
     */
    private function mockWebsite(array $stores): MockObject
    {
        $website = $this->createMock(Website::class);
        $website->method('getStores')->willReturn($stores);
        return $website;
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassHideAtSpecificStoreQueuesDiscontinueOnlyForThatStore(): void
    {
        $this->productCollectionMock->method('getItems')->willReturn([
            $this->mockProductItem(11, Visibility::VISIBILITY_BOTH),
            $this->mockProductItem(22, Visibility::VISIBILITY_NOT_VISIBLE),
        ]);

        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getStore')->with(5)->willReturn($store);

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([11], $store);

        $result = $this->plugin->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [11, 22],
            ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
            5
        );

        $this->assertSame('updated', $result);
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassHideAtDefaultScopeQueuesDiscontinueAcrossAllAssignedStores(): void
    {
        $this->productCollectionMock->method('getItems')->willReturn([
            $this->mockProductItem(11, Visibility::VISIBILITY_BOTH),
        ]);
        $this->nostoHelperScopeMock->method('getStore')->with(0)->willReturn($this->createMock(Store::class));

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->with(11)->willReturn(['2']);

        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$storeA, $storeB]));

        $deleteQueueCalls = [];
        $this->productUpdateServiceMock->expects($this->exactly(2))
            ->method('addIdsToDeleteMessageQueue')
            ->willReturnCallback(function (array $ids, $store) use (&$deleteQueueCalls) {
                $deleteQueueCalls[] = [$ids, $store];
            });

        $this->plugin->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [11],
            ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
            0
        );

        $this->assertSame([[11], $storeA], $deleteQueueCalls[0]);
        $this->assertSame([[11], $storeB], $deleteQueueCalls[1]);
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassUpdateOfUnrelatedAttributeDoesNotQueueDiscontinue(): void
    {
        $this->productCollectionBuilderMock->expects($this->never())->method('withIds');
        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $result = $this->plugin->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [11, 22],
            ['status' => 2],
            0
        );

        $this->assertSame('updated', $result);
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassHideOfAlreadyHiddenProductsDoesNotQueueDiscontinue(): void
    {
        $this->productCollectionMock->method('getItems')->willReturn([
            $this->mockProductItem(11, Visibility::VISIBILITY_NOT_VISIBLE),
        ]);
        $this->nostoHelperScopeMock->method('getStore')->with(0)->willReturn($this->createMock(Store::class));

        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [11],
            ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
            0
        );
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testEmptyProductIdsDoesNothing(): void
    {
        $this->productCollectionBuilderMock->expects($this->never())->method('withIds');
        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [],
            ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
            0
        );
    }
}
