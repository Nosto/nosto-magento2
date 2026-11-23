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
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\VisibilityResolver;
use Nosto\Tagging\Model\ResourceModel\Product\WebsiteLink;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use Nosto\Tagging\Plugin\ProductVisibilityUpdate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ProductVisibilityUpdateTest extends TestCase
{
    /** @var ProductVisibilityUpdate */
    private ?ProductVisibilityUpdate $plugin = null;

    /** @var ProductAction&Stub */
    private Stub $productActionMock;

    /** @var VisibilityResolver&Stub */
    private Stub $visibilityResolverMock;

    /** @var WebsiteLink&Stub */
    private Stub $productWebsiteLinkMock;

    /** @var NostoHelperScope&Stub */
    private Stub $nostoHelperScopeMock;

    /** @var ProductUpdateService&MockObject */
    private MockObject $productUpdateServiceMock;

    /** @var NostoLogger&Stub */
    private Stub $loggerMock;

    protected function setUp(): void
    {
        $this->productActionMock = $this->createStub(ProductAction::class);
        $this->visibilityResolverMock = $this->createStub(VisibilityResolver::class);
        $this->productWebsiteLinkMock = $this->createStub(WebsiteLink::class);
        $this->nostoHelperScopeMock = $this->createStub(NostoHelperScope::class);
        $this->productUpdateServiceMock = $this->createMock(ProductUpdateService::class);
        $this->loggerMock = $this->createStub(NostoLogger::class);
    }

    private function getProductVisibilityUpdate(): ProductVisibilityUpdate
    {
        return $this->plugin ??= new ProductVisibilityUpdate(
            $this->visibilityResolverMock,
            $this->productWebsiteLinkMock,
            $this->nostoHelperScopeMock,
            $this->productUpdateServiceMock,
            $this->loggerMock
        );
    }

    /**
     * @param Store[] $stores
     * @return Website&Stub
     */
    private function mockWebsite(array $stores): Stub
    {
        $website = $this->createStub(Website::class);
        $website->method('getStores')->willReturn($stores);
        return $website;
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassHideAtSpecificStoreQueuesDiscontinueOnlyForThatStore(): void
    {
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->loggerMock = $this->createMock(NostoLogger::class);

        $store = $this->createStub(Store::class);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getStore')->with(5)->willReturn($store);

        $this->visibilityResolverMock->expects($this->once())
            ->method('getIndividuallyVisibleProductIds')
            ->with([11, 22], $store)
            ->willReturn([11]);

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([11], $store);
        $this->loggerMock->expects($this->once())->method('debug');

        $result = $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
        $this->productWebsiteLinkMock = $this->createMock(WebsiteLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $defaultStore = $this->createStub(Store::class);
        $storeA = $this->createStub(Store::class);
        $storeB = $this->createStub(Store::class);
        $storeA->method('getId')->willReturn(1);
        $storeB->method('getId')->willReturn(2);

        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getStore')->with(0)->willReturn($defaultStore);
        $this->productWebsiteLinkMock->expects($this->once())
            ->method('getWebsiteIdsByProductIds')
            ->with([11])
            ->willReturn([11 => [2]]);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$storeA, $storeB]));

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

        $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
        $this->productWebsiteLinkMock = $this->createMock(WebsiteLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->loggerMock = $this->createMock(NostoLogger::class);

        $defaultStore = $this->createStub(Store::class);
        $storeA = $this->createStub(Store::class);
        $storeB = $this->createStub(Store::class);
        $storeA->method('getId')->willReturn(1);
        $storeB->method('getId')->willReturn(2);

        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getStore')->with(0)->willReturn($defaultStore);
        $this->productWebsiteLinkMock->expects($this->atLeastOnce())->method('getWebsiteIdsByProductIds')->with([11])->willReturn([11 => [2]]);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$storeA, $storeB]));

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
        $this->loggerMock->expects($this->once())->method('debug');

        $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
    public function testMassHideAtDefaultScopeBatchesMultipleProductsIntoOneQueryPerStore(): void
    {
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);
        $this->productWebsiteLinkMock = $this->createMock(WebsiteLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $defaultStore = $this->createStub(Store::class);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getStore')->with(0)->willReturn($defaultStore);

        // All three products share the same website/store.
        $this->productWebsiteLinkMock->expects($this->once())
            ->method('getWebsiteIdsByProductIds')
            ->with([11, 22, 33])
            ->willReturn([11 => [2], 22 => [2], 33 => [2]]);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getWebsite')->with(2)->willReturn($this->mockWebsite([$store]));

        // A single batched call for the whole group, not one call per product.
        $this->visibilityResolverMock->expects($this->exactly(2))
            ->method('getIndividuallyVisibleProductIds')
            ->willReturnCallback(function (array $ids, Store $s) use ($defaultStore, $store) {
                if ($s === $defaultStore) {
                    return [11, 22, 33];
                }
                $this->assertSame($store, $s);
                $this->assertSame([11, 22, 33], $ids);
                return [];
            });

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([11, 22, 33], $store);

        $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
            $this->productActionMock,
            function () {
                return 'updated';
            },
            [11, 22, 33],
            ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
            0
        );
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductVisibilityUpdate::aroundUpdateAttributes()
     */
    public function testMassUpdateOfUnrelatedAttributeDoesNotQueueDiscontinue(): void
    {
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);

        $this->visibilityResolverMock->expects($this->never())->method('getIndividuallyVisibleProductIds');
        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $result = $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
        $this->productWebsiteLinkMock = $this->createMock(WebsiteLink::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('getStore')->with(0)->willReturn($this->createStub(Store::class));
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')->willReturn([]);

        $this->productWebsiteLinkMock->expects($this->never())->method('getWebsiteIdsByProductIds');
        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);

        $this->visibilityResolverMock->expects($this->never())->method('getIndividuallyVisibleProductIds');
        $this->productUpdateServiceMock->expects($this->never())->method('addIdsToDeleteMessageQueue');

        $this->getProductVisibilityUpdate()->aroundUpdateAttributes(
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
