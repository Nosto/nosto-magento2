<?php

/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

declare(strict_types=1);

namespace Nosto\Tagging\Test\Unit\Model\ResourceModel;

use Magento\Eav\Model\Entity\AbstractEntity;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Website;
use Nosto\Tagging\Model\ResourceModel\Sku;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SkuTest extends TestCase
{
    /** @var Sku|MockObject */
    private Sku $sku;

    /** @var ResourceConnection|MockObject */
    private MockObject $resourceConnectionMock;

    /** @var AdapterInterface|MockObject */
    private MockObject $connectionMock;

    /** @var Select|MockObject */
    private MockObject $selectMock;

    /** @var Website|MockObject */
    private MockObject $websiteMock;

    protected function setUp(): void
    {
        $this->sku = $this->getMockBuilder(Sku::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->selectMock = $this->createMock(Select::class);
        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();
        $this->selectMock->method('order')->willReturnSelf();
        $this->selectMock->method('limit')->willReturnSelf();

        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->connectionMock->method('select')->willReturn($this->selectMock);

        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceConnectionMock->method('getTableName')
            ->willReturn(Sku::CATALOG_PRODUCT_PRICE_INDEX_TABLE);

        $resourceProperty = new \ReflectionProperty(AbstractEntity::class, '_resource');
        $resourceProperty->setValue($this->sku, $this->resourceConnectionMock);

        $this->websiteMock = $this->createMock(Website::class);
        $this->websiteMock->method('getId')->willReturn('1');
    }

    /**
     * @covers Sku::getMinPriceSkuId()
     */
    public function testGetMinPriceSkuIdReturnsNullForEmptySkuIds(): void
    {
        $this->connectionMock->expects($this->never())->method('fetchOne');

        $result = $this->sku->getMinPriceSkuId($this->websiteMock, 1, []);

        $this->assertNull($result);
    }

    /**
     * @covers Sku::getMinPriceSkuId()
     */
    public function testGetMinPriceSkuIdReturnsEntityIdWhenFound(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn('42');

        $result = $this->sku->getMinPriceSkuId($this->websiteMock, 1, [10, 20, 30]);

        $this->assertSame(42, $result);
    }

    /**
     * @covers Sku::getMinPriceSkuId()
     */
    public function testGetMinPriceSkuIdReturnsNullWhenNoIndexRow(): void
    {
        $this->connectionMock->method('fetchOne')->willReturn(false);

        $result = $this->sku->getMinPriceSkuId($this->websiteMock, 1, [10, 20, 30]);

        $this->assertNull($result);
    }
}
