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

use Magento\Catalog\Api\Data\CategoryInterface;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection
 */
class CollectionTest extends TestCase
{
    /**
     * @return Collection|MockObject
     */
    private function collectionMock(): MockObject
    {
        return $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter'])
            ->getMock();
    }

    /**
     * @covers ::addRootCategoryFilter
     */
    public function testFiltersToRootCategoryItselfAndItsDescendantsOnly(): void
    {
        // NS-14595: only the root category itself, or a descendant whose path starts
        // with "<rootPath>/", may match. A plain "path LIKE '<rootPath>%'" would also
        // match an unrelated root category whose id happens to be a numeric prefix of
        // this one (e.g. root id 128 vs. root id 1286), leaking categories from another
        // website/store into this store's Nosto account.
        $collection = $this->collectionMock();

        $rootCategory = $this->createMock(CategoryInterface::class);
        $rootCategory->method('getPath')->willReturn('1/1286');

        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(
                'path',
                [
                    ['eq' => '1/1286'],
                    ['like' => '1/1286/%']
                ]
            )
            ->willReturnSelf();

        $result = $collection->addRootCategoryFilter($rootCategory);

        $this->assertSame($collection, $result);
    }
}
