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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as MagentoResourceProduct;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\Product\VisibilityResolver;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use Nosto\Tagging\Plugin\ProductUpdate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductUpdateTest extends TestCase
{
    private const PRODUCT_ID = 123;

    /** @var ProductUpdate */
    private ProductUpdate $plugin;

    /** @var MagentoResourceProduct|MockObject */
    private MockObject $productResourceMock;

    /** @var Product|MockObject */
    private MockObject $productMock;

    /** @var IndexerInterface|MockObject */
    private MockObject $indexerMock;

    /** @var ProductUpdateService|MockObject */
    private MockObject $productUpdateServiceMock;

    /** @var NostoHelperScope|MockObject */
    private MockObject $nostoHelperScopeMock;

    /** @var ProductStoreLink|MockObject */
    private MockObject $productStoreLinkMock;

    /** @var VisibilityResolver|MockObject */
    private MockObject $visibilityResolverMock;

    /** @var callable[] */
    private array $commitCallbacks = [];

    protected function setUp(): void
    {
        $this->commitCallbacks = [];

        $this->indexerMock = $this->createMock(IndexerInterface::class);
        $indexerRegistryMock = $this->createMock(IndexerRegistry::class);
        $indexerRegistryMock->method('get')->willReturn($this->indexerMock);

        $this->productResourceMock = $this->createMock(MagentoResourceProduct::class);
        $this->productResourceMock->method('addCommitCallback')
            ->willReturnCallback(function ($callback) {
                $this->commitCallbacks[] = $callback;
                return $this->productResourceMock;
            });

        $this->productMock = $this->createMock(Product::class);
        $this->productMock->method('getId')->willReturn(self::PRODUCT_ID);

        $this->productUpdateServiceMock = $this->createMock(ProductUpdateService::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->productStoreLinkMock = $this->createMock(ProductStoreLink::class);
        $this->visibilityResolverMock = $this->createMock(VisibilityResolver::class);

        $this->plugin = new ProductUpdate(
            $indexerRegistryMock,
            $this->createMock(ProductIndexer::class),
            $this->createMock(NostoProductRepository::class),
            $this->createMock(NostoLogger::class),
            $this->productUpdateServiceMock,
            $this->nostoHelperScopeMock,
            $this->createMock(CollectionBuilder::class),
            $this->productStoreLinkMock,
            $this->visibilityResolverMock
        );
    }

    private function runCommitCallbacks(): void
    {
        foreach ($this->commitCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * @param Store[] $stores
     * @return Store|MockObject
     */
    private function mockStore(array $stores): MockObject
    {
        $store = $this->createMock(Website::class);
        $store->method('getStores')->willReturn($stores);
        return $store;
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveQueuesDiscontinueForStoresOfRemovedStores(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        // Store 2 is removed by this save, Stores 1 & 7 remain
        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')
            ->willReturnOnConsecutiveCalls(['1', '2', '7'], ['1', '7']);

        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')
            ->with(2)
            ->willReturn($this->mockStore([$storeA, $storeB]));

        $deleteQueueCalls = [];
        $this->productUpdateServiceMock->expects($this->exactly(2))
            ->method('addIdsToDeleteMessageQueue')
            ->willReturnCallback(function (array $ids, $store) use (&$deleteQueueCalls) {
                $deleteQueueCalls[] = [$ids, $store];
            });

        $result = $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );

        $this->assertSame('saved', $result);
        $this->runCommitCallbacks();

        $this->assertSame([[self::PRODUCT_ID], $storeA], $deleteQueueCalls[0]);
        $this->assertSame([[self::PRODUCT_ID], $storeB], $deleteQueueCalls[1]);
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveDoesNotQueueDiscontinueWhenStoresAreUnchanged(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')
            ->willReturnOnConsecutiveCalls(['1', '7'], ['1', '7']);

        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveDoesNotQueueDiscontinueWhenStoresAreOnlyAdded(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')
            ->willReturnOnConsecutiveCalls(['1'], ['1', '7']);

        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveDoesNotReadStoresForNewProducts(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(null);

        $this->productStoreLinkMock->expects($this->never())->method('getWebsiteIdsByProductId');
        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $newProduct
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveQueuesDiscontinueAlsoWhenIndexerIsScheduled(): void
    {
        // In "Update by schedule" mode mview cannot produce discontinue signals either,
        // so the Store diff must be handled by the plugin in both indexer modes
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')
            ->willReturnOnConsecutiveCalls(['2'], []);

        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')
            ->with(2)
            ->willReturn($this->mockStore([$store]));

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([self::PRODUCT_ID], $store);

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveQueuesDiscontinueWhenProductBecomesNotIndividuallyVisible(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productMock->method('getOrigData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_BOTH);
        $this->productMock->method('getData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productMock->method('getStoreId')->willReturn(0);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->willReturn(['2']);

        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')
            ->with(2)
            ->willReturn($this->mockStore([$store]));

        // No store-level override, so the store inherits the new hidden default.
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')
            ->with([self::PRODUCT_ID], $store)
            ->willReturn([]);

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([self::PRODUCT_ID], $store);

        $result = $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );

        $this->assertSame('saved', $result);
        $this->runCommitCallbacks();
    }

    /**
     * A partially loaded product carries no original visibility value. The product is
     * hidden after the save either way, so the discontinue must still be sent rather
     * than dropped because the previous value is unknown.
     *
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveQueuesDiscontinueWhenTheOriginalVisibilityIsUnknown(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productMock->method('getOrigData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(null);
        $this->productMock->method('getData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productMock->method('getStoreId')->willReturn(3);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->willReturn(['2']);

        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getStore')->with(3)->willReturn($store);

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([self::PRODUCT_ID], $store);

        $result = $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );

        $this->assertSame('saved', $result);
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveQueuesDiscontinueOnlyForTheEditedStoreWhenStoreScoped(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productMock->method('getOrigData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_BOTH);
        $this->productMock->method('getData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productMock->method('getStoreId')->willReturn(5);

        $store = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getStore')->with(5)->willReturn($store);

        // getWebsiteIdsByProductId still fires for the unrelated store-removal check
        // (see getPersistedStoreIds); the store-scoped visibility change must not add
        // any further calls to it, since that store is already known.
        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->willReturn(['1']);
        $this->visibilityResolverMock->expects($this->never())->method('getIndividuallyVisibleProductIds');

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([self::PRODUCT_ID], $store);

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveSkipsStoresWithTheirOwnVisibilityOverrideAtDefaultScope(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productMock->method('getOrigData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_BOTH);
        $this->productMock->method('getData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productMock->method('getStoreId')->willReturn(0);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->willReturn(['2']);

        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')
            ->with(2)
            ->willReturn($this->mockStore([$storeA, $storeB]));

        // Store B has its own visibility override that keeps it visible.
        $this->visibilityResolverMock->method('getIndividuallyVisibleProductIds')
            ->willReturnCallback(function (array $ids, Store $store) use ($storeB) {
                return $store === $storeB ? [self::PRODUCT_ID] : [];
            });

        $this->productUpdateServiceMock->expects($this->once())
            ->method('addIdsToDeleteMessageQueue')
            ->with([self::PRODUCT_ID], $storeA);

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveDoesNotQueueDiscontinueWhenVisibilityIsUnchanged(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $this->productMock->method('getOrigData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_BOTH);
        $this->productMock->method('getData')
            ->with(ProductInterface::VISIBILITY)
            ->willReturn(Visibility::VISIBILITY_BOTH);

        $this->productStoreLinkMock->method('getWebsiteIdsByProductId')->willReturn(['2']);

        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $this->productMock
        );
        $this->runCommitCallbacks();
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductUpdate::aroundSave()
     */
    public function testAroundSaveDoesNotQueueDiscontinueForVisibilityOnNewProducts(): void
    {
        $this->indexerMock->method('isScheduled')->willReturn(true);

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(null);
        $newProduct->expects($this->never())->method('getOrigData');

        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->aroundSave(
            $this->productResourceMock,
            function () {
                return 'saved';
            },
            $newProduct
        );
        $this->runCommitCallbacks();
    }
}
