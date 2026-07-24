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

namespace Nosto\Tagging\Test\Unit\Model\Service\Update;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Nosto\Tagging\Exception\ParentProductDisabledException;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @coversDefaultClass \Nosto\Tagging\Model\Service\Update\ProductUpdateService
 */
class ProductUpdateServiceTest extends TestCase
{
    /** @var ProductUpdateService */
    private ProductUpdateService $service;

    /** @var NostoProductRepository|MockObject */
    private MockObject $repositoryMock;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(NostoProductRepository::class);

        $this->service = new ProductUpdateService(
            $this->createMock(NostoLogger::class),
            $this->createMock(NostoDataHelper::class),
            $this->createMock(NostoAccountHelper::class),
            $this->repositoryMock,
            $this->createMock(BulkPublisherInterface::class),
            $this->createMock(BulkPublisherInterface::class),
            100
        );
    }

    /**
     * @param int $id
     * @param int|null $visibility
     * @return Product|MockObject
     */
    private function productMock(int $id, ?int $visibility): MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getVisibility')->willReturn($visibility);
        return $product;
    }

    /**
     * @param Product[] $products
     * @return int[]
     */
    private function resolveIds(array $products): array
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('getItems')->willReturn($products);
        $method = new ReflectionMethod(ProductUpdateService::class, 'getEntityIdsForPage');
        $method->setAccessible(true);
        return $method->invoke($this->service, $collection);
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testStandaloneProductReturnsOwnId(): void
    {
        $product = $this->productMock(10, Visibility::VISIBILITY_BOTH);
        $this->repositoryMock->method('resolveParentProductIds')->willReturn(null);

        $this->assertSame([10], $this->resolveIds([$product]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testNonVisibleChildReturnsOnlyParent(): void
    {
        // standard configurable child (not individually visible) -> parent only (unchanged behavior)
        $child = $this->productMock(11, Visibility::VISIBILITY_NOT_VISIBLE);
        $this->repositoryMock->method('resolveParentProductIds')->willReturn([99]);

        $this->assertSame([99], $this->resolveIds([$child]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testVisibleChildReturnsParentAndOwnId(): void
    {
        // NS-14371: an individually visible variant is its own Nosto product,
        // so disabling it must sync the child itself in addition to its parent.
        $child = $this->productMock(12, Visibility::VISIBILITY_IN_SEARCH);
        $this->repositoryMock->method('resolveParentProductIds')->willReturn([99]);

        $this->assertSame([99, 12], $this->resolveIds([$child]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testVisibleChildWithAllParentsDisabledReturnsOwnId(): void
    {
        // NS-14371: parents disabled -> exception; a visible child must still be synced
        $child = $this->productMock(13, Visibility::VISIBILITY_IN_CATALOG);
        $this->repositoryMock->method('resolveParentProductIds')
            ->willThrowException(new ParentProductDisabledException(13));

        $this->assertSame([13], $this->resolveIds([$child]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testNonVisibleChildWithAllParentsDisabledReturnsNothing(): void
    {
        // safety: a hidden child with disabled parents is not its own product -> skip (unchanged)
        $child = $this->productMock(14, Visibility::VISIBILITY_NOT_VISIBLE);
        $this->repositoryMock->method('resolveParentProductIds')
            ->willThrowException(new ParentProductDisabledException(14));

        $this->assertSame([], $this->resolveIds([$child]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testUnloadedVisibilityFallsBackToParentOnly(): void
    {
        // Safety: if visibility is not loaded on the collection, a child must NOT be
        // treated as individually visible, otherwise standard hidden variations would
        // be queued on their own.
        $child = $this->productMock(15, null);
        $this->repositoryMock->method('resolveParentProductIds')->willReturn([99]);

        $this->assertSame([99], $this->resolveIds([$child]));
    }

    /**
     * @covers ::getEntityIdsForPage
     */
    public function testResultIsSequentiallyIndexedAfterDedup(): void
    {
        // Two visible children sharing one parent: parent id de-duplicates, and the
        // returned array must stay sequentially indexed (so it JSON-encodes as an array).
        $childA = $this->productMock(20, Visibility::VISIBILITY_BOTH);
        $childB = $this->productMock(21, Visibility::VISIBILITY_BOTH);
        $this->repositoryMock->method('resolveParentProductIds')->willReturn([99]);

        $result = $this->resolveIds([$childA, $childB]);

        $this->assertSame([99, 20, 21], $result);
        $this->assertSame(range(0, count($result) - 1), array_keys($result));
    }
}
