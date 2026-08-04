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

namespace Nosto\Tagging\Test\Unit\Model\ResourceModel\Product;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Nosto\Tagging\Model\ResourceModel\Product\WebsiteLink;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebsiteLinkTest extends TestCase
{
    /** @var WebsiteLink */
    private WebsiteLink $websiteLink;

    /** @var AdapterInterface|MockObject */
    private MockObject $connectionMock;

    protected function setUp(): void
    {
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->connectionMock->method('select')->willReturn($selectMock);

        $resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $resourceConnectionMock->method('getTableName')->willReturn('catalog_product_website');

        $this->websiteLink = new WebsiteLink($resourceConnectionMock);
    }

    /**
     * @covers \Nosto\Tagging\Model\ResourceModel\Product\WebsiteLink::getWebsiteIdsByProductIds()
     */
    public function testGroupsWebsiteIdsByProductIdInOneQuery(): void
    {
        $this->connectionMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['product_id' => '11', 'website_id' => '1'],
                ['product_id' => '11', 'website_id' => '2'],
                ['product_id' => '22', 'website_id' => '1'],
            ]);

        $result = $this->websiteLink->getWebsiteIdsByProductIds([11, 22]);

        $this->assertSame([11 => [1, 2], 22 => [1]], $result);
    }

    /**
     * @covers \Nosto\Tagging\Model\ResourceModel\Product\WebsiteLink::getWebsiteIdsByProductIds()
     */
    public function testEmptyProductIdsReturnsEmptyArrayWithoutQuerying(): void
    {
        $this->connectionMock->expects($this->never())->method('fetchAll');

        $result = $this->websiteLink->getWebsiteIdsByProductIds([]);

        $this->assertSame([], $result);
    }
}
