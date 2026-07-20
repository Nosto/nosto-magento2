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
use Magento\Store\Model\Store;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;
use Nosto\Tagging\Plugin\ProductWebsiteUpdate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductWebsiteUpdateTest extends TestCase
{
    /** @var ProductWebsiteUpdate */
    private ProductWebsiteUpdate $plugin;

    /** @var ProductAction|MockObject */
    private MockObject $productActionMock;

    /** @var ProductUpdateService|MockObject */
    private MockObject $productUpdateServiceMock;

    /** @var NostoHelperScope|MockObject */
    private MockObject $nostoHelperScopeMock;

    protected function setUp(): void
    {
        $this->productActionMock = $this->createMock(ProductAction::class);
        $this->productUpdateServiceMock = $this->createMock(ProductUpdateService::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $this->plugin = new ProductWebsiteUpdate(
            $this->productUpdateServiceMock,
            $this->nostoHelperScopeMock,
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
     * @covers \Nosto\Tagging\Plugin\ProductWebsiteUpdate::afterUpdateWebsites()
     */
    public function testMassWebsiteRemovalQueuesDiscontinueForStoresOfRemovedWebsites(): void
    {
        $productIds = [11, 22, 33];

        $storeA = $this->createMock(Store::class);
        $storeB = $this->createMock(Store::class);
        $this->nostoHelperScopeMock->method('getWebsite')
            ->willReturnMap([
                [2, $this->mockWebsite([$storeA])],
                [7, $this->mockWebsite([$storeB])],
            ]);

        $deleteQueueCalls = [];
        $this->productUpdateServiceMock->expects($this->exactly(2))
            ->method('addIdsToDeleteMessageQueue')
            ->willReturnCallback(function (array $ids, $store) use (&$deleteQueueCalls) {
                $deleteQueueCalls[] = [$ids, $store];
            });

        $this->plugin->afterUpdateWebsites(
            $this->productActionMock,
            null,
            $productIds,
            [2, 7],
            'remove'
        );

        $this->assertSame([$productIds, $storeA], $deleteQueueCalls[0]);
        $this->assertSame([$productIds, $storeB], $deleteQueueCalls[1]);
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductWebsiteUpdate::afterUpdateWebsites()
     */
    public function testMassWebsiteAdditionDoesNotQueueDiscontinue(): void
    {
        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->afterUpdateWebsites(
            $this->productActionMock,
            null,
            [11, 22],
            [2],
            'add'
        );
    }

    /**
     * @covers \Nosto\Tagging\Plugin\ProductWebsiteUpdate::afterUpdateWebsites()
     */
    public function testEmptyProductOrWebsiteListsDoNothing(): void
    {
        $this->productUpdateServiceMock->expects($this->never())
            ->method('addIdsToDeleteMessageQueue');

        $this->plugin->afterUpdateWebsites($this->productActionMock, null, [], [2], 'remove');
        $this->plugin->afterUpdateWebsites($this->productActionMock, null, [11], [], 'remove');
    }
}
