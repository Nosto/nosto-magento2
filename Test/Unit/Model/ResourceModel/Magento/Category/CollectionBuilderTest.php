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

namespace Nosto\Tagging\Test\Unit\Model\ResourceModel\Magento\Category;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionBuilder
 */
class CollectionBuilderTest extends TestCase
{
    /** @var CategoryCollection|MockObject */
    private MockObject $collectionMock;

    /** @var CategoryRepositoryInterface|MockObject */
    private MockObject $categoryRepositoryMock;

    private CollectionBuilder $builder;

    protected function setUp(): void
    {
        $this->collectionMock = $this->createMock(CategoryCollection::class);
        $this->categoryRepositoryMock = $this->createMock(CategoryRepositoryInterface::class);

        $this->builder = new CollectionBuilder(
            $this->collectionMock,
            $this->categoryRepositoryMock
        );
    }

    /**
     * @param int $storeId
     * @param int $rootCategoryId
     * @return Store|MockObject
     */
    private function storeMock(int $storeId, int $rootCategoryId): MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getRootCategoryId')->willReturn($rootCategoryId);
        return $store;
    }

    /**
     * @covers ::withStore
     */
    public function testWithStoreScopesCollectionToTheStoresOwnRootCategory(): void
    {
        // NS-14595: building a collection for a given store must restrict it to that
        // store's own root category tree. Without this, a category belonging to a
        // completely different website/store (with its own root category) would be
        // included and end up synced to the wrong Nosto account.
        $store = $this->storeMock(5, 1286);
        $rootCategory = $this->createMock(CategoryInterface::class);

        $this->categoryRepositoryMock->expects($this->once())
            ->method('get')
            ->with(1286, 5)
            ->willReturn($rootCategory);

        $this->collectionMock->expects($this->once())
            ->method('addRootCategoryFilter')
            ->with($rootCategory)
            ->willReturnSelf();

        $this->collectionMock->expects($this->once())
            ->method('setProductStoreId')
            ->with(5);
        $this->collectionMock->expects($this->once())
            ->method('setStore')
            ->with($store);

        $result = $this->builder->withStore($store);

        $this->assertSame($this->builder, $result);
    }

    /**
     * @covers ::withStore
     */
    public function testWithStoreUsesEachStoresOwnRootCategoryIndependently(): void
    {
        // Two stores with two different root categories (e.g. "Main Website" and
        // "iStore Education" from NS-14595) must each be scoped to their own root
        // category, not a shared/leftover one.
        $mainStore = $this->storeMock(1, 2);
        $educationStore = $this->storeMock(5, 1286);

        $mainRoot = $this->createMock(CategoryInterface::class);
        $educationRoot = $this->createMock(CategoryInterface::class);

        $this->categoryRepositoryMock->method('get')
            ->willReturnMap([
                [2, 1, $mainRoot],
                [1286, 5, $educationRoot],
            ]);

        $this->collectionMock->expects($this->exactly(2))
            ->method('addRootCategoryFilter')
            ->willReturnSelf();

        $this->builder->withStore($mainStore);
        $this->builder->withStore($educationStore);
    }
}
