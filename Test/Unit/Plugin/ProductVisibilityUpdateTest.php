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

use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\VisibilityResolver;
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

    /** @var VisibilityResolver|MockObject */
    private MockObject $visibilityResolverMock;

    /** @var ProductStoreLink|MockObject */
    private MockObject $productStoreLinkMock;

    /** @var NostoHelperScope|MockObject */
    private MockObject $nostoHelperScopeMock;

    /** @var ProductUpdateService|MockObject */
    private MockObject $productUpdateServiceMock;

    protected function setUp(): void
    {
        $this->productActionMock = $this->createMock(ProductAction::class);
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);
        $this->productStoreLinkMock = $this->createMock(ProductStoreLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->productUpdateServiceMock = $this->createMock(ProductUpdateService::class);

        $this->plugin = new ProductVisibilityUpdate(
            $this->visibilityResolverMock,
            $this->productStoreLinkMock,
            $this->nostoHelperScopeMock,
            $this->productUpdateServiceMock,
            $this->createMock(NostoLogger::class)
        );
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
        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getStore')->with(5)->willReturn($store);

        $this->visibilityResolverMock->expects($this->once())
            ->method('getIndividuallyVisibleProductIds')
            ->with([11, 22], $store)
            ->willReturn([11]);

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
        $defaultStore = $this->createMock(Store::class);
        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);

        $this->nostoHelperScopeMock->method('getStore')->with(0)->willReturn($defaultStore);
        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->with(11)->willReturn(['2']);
        $this->nostoHelperScopeMock->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$storeA, $storeB]));

        // Neither store has its own override, so both now resolve to hidden.
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')
            ->willReturnCallback(function (array $ids, Store $store) use ($defaultStore) {
                return $store === $defaultStore ? [11] : [];
            });

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
    public function testMassHideAtDefaultScopeSkipsStoresWithTheirOwnOverride(): void
    {
        $defaultStore = $this->createMock(Store::class);
        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);

        $this->nostoHelperScopeMock->method('getStore')->with(0)->willReturn($defaultStore);
        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->with(11)->willReturn(['2']);
        $this->nostoHelperScopeMock->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$storeA, $storeB]));

        // Store B has its own visibility override that keeps it visible, so it must
        // not be discontinued just because the default value changed.
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')
            ->willReturnCallback(function (array $ids, Store $store) use ($defaultStore, $storeB) {
                if ($store === $defaultStore) {
                    return [11];
                }
                return $store === $storeB ? [11] : [];
            });

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([11], $storeA);

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
    public function testMassUpdateOfUnrelatedAttributeDoesNotQueueDiscontinue(): void
    {
        $this->visibilityResolverMock->expects($this->never())->method('getIndividuallyVisibleProductIds');
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
        $this->nostoHelperScopeMock->method('getStore')->with(0)->willReturn($this->createMock(Store::class));
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')->willReturn([]);

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
        $this->visibilityResolverMock->expects($this->never())->method('getIndividuallyVisibleProductIds');
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
