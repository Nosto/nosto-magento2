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

namespace Nosto\Tagging\Test\Unit\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\Product\VisibilityResolver;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VisibilityResolverTest extends TestCase
{
    /** @var VisibilityResolver */
    private VisibilityResolver $resolver;

    /** @var CollectionBuilder|MockObject */
    private MockObject $collectionBuilderMock;

    /** @var ProductCollection|MockObject */
    private MockObject $collectionMock;

    protected function setUp(): void
    {
        $this->collectionMock = $this->createMock(ProductCollection::class);

        $this->collectionBuilderMock = $this->createMock(CollectionBuilder::class);
        $this->collectionBuilderMock->method('reset')->willReturnSelf();
        $this->collectionBuilderMock->method('withStore')->willReturnSelf();
        $this->collectionBuilderMock->method('withIds')->willReturnSelf();
        $this->collectionBuilderMock->method('build')->willReturn($this->collectionMock);

        $this->resolver = new VisibilityResolver($this->collectionBuilderMock);
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
     * @covers \Nosto\Tagging\Model\Product\VisibilityResolver::getIndividuallyVisibleProductIds()
     */
    public function testReturnsOnlyIdsThatAreIndividuallyVisibleAtTheGivenStore(): void
    {
        $this->collectionMock->method('getItems')->willReturn([
            $this->mockProductItem(11, Visibility::VISIBILITY_BOTH),
            $this->mockProductItem(22, Visibility::VISIBILITY_NOT_VISIBLE),
            $this->mockProductItem(33, Visibility::VISIBILITY_IN_SEARCH),
        ]);

        $store = $this->createMock(Store::class);
        $this->collectionBuilderMock->expects($this->once())->method('withStore')->with($store)->willReturnSelf();
        $this->collectionBuilderMock->expects($this->once())->method('withIds')->with([11, 22, 33])->willReturnSelf();

        $result = $this->resolver->getIndividuallyVisibleProductIds([11, 22, 33], $store);

        $this->assertSame([11, 33], $result);
    }

    /**
     * @covers \Nosto\Tagging\Model\Product\VisibilityResolver::getIndividuallyVisibleProductIds()
     */
    public function testEmptyProductIdsReturnsEmptyWithoutBuildingCollection(): void
    {
        $this->collectionBuilderMock->expects($this->never())->method('withIds');

        $result = $this->resolver->getIndividuallyVisibleProductIds([], $this->createMock(Store::class));

        $this->assertSame([], $result);
    }

    /**
     * @covers \Nosto\Tagging\Model\Product\VisibilityResolver::getIndividuallyVisibleProductIds()
     */
    public function testResetsTheBuilderBeforeEveryQuerySoSequentialStoresDoNotMix(): void
    {
        $this->collectionMock->method('getItems')->willReturn([]);

        $this->collectionBuilderMock->expects($this->exactly(2))->method('reset')->willReturnSelf();

        $this->resolver->getIndividuallyVisibleProductIds([11], $this->createMock(Store::class));
        $this->resolver->getIndividuallyVisibleProductIds([22], $this->createMock(Store::class));
    }
}
