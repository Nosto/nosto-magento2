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

namespace Nosto\Tagging\Test\Unit\Block;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Block\Addtocart;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddtocartTest extends TestCase
{
    /** @var Context|MockObject */
    private $contextMock;

    /** @var EncoderInterface|MockObject */
    private $urlEncoderMock;

    /** @var NostoHelperAccount|MockObject */
    private $nostoHelperAccountMock;

    /** @var NostoHelperScope|MockObject */
    private $nostoHelperScopeMock;

    /** @var NostoHelperData|MockObject */
    private $nostoHelperDataMock;

    /** @var UrlInterface|MockObject */
    private $urlBuilderMock;

    /** @var RequestInterface|MockObject */
    private $requestMock;

    /** @var Store|MockObject */
    private $storeMock;

    private Addtocart $block;

    protected function setUp(): void
    {
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->storeMock = $this->createMock(Store::class);

        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contextMock->method('getUrlBuilder')->willReturn($this->urlBuilderMock);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        $this->urlEncoderMock = $this->createMock(EncoderInterface::class);
        $this->nostoHelperAccountMock = $this->createMock(NostoHelperAccount::class);
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);
        $this->nostoHelperDataMock = $this->createMock(NostoHelperData::class);

        $this->block = new Addtocart(
            $this->contextMock,
            $this->urlEncoderMock,
            $this->nostoHelperAccountMock,
            $this->nostoHelperScopeMock,
            $this->nostoHelperDataMock
        );
    }

    /**
     * @covers Addtocart::getAbstractObject()
     * @return void
     */
    public function testGetAbstractObjectReturnsNull(): void
    {
        $this->assertNull($this->block->getAbstractObject());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsTrueWhenEnabled(): void
    {
        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->nostoHelperScopeMock->method('isHyvaEnabled')
            ->with($this->storeMock)
            ->willReturn(true);

        $this->assertTrue($this->block->isHyva());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsFalseWhenDisabled(): void
    {
        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->nostoHelperScopeMock->method('isHyvaEnabled')
            ->with($this->storeMock)
            ->willReturn(false);

        $this->assertFalse($this->block->isHyva());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsFalseOnException(): void
    {
        $this->nostoHelperScopeMock->method('getStore')
            ->willThrowException(new \Exception('Store not found'));

        $this->assertFalse($this->block->isHyva());
    }

    /**
     * @covers Addtocart::getSubmitUrl()
     * @return void
     */
    public function testGetSubmitUrl(): void
    {
        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getCode')->willReturn('default');
        $this->nostoHelperDataMock->method('getStoreCodeToUrl')
            ->with($this->storeMock)
            ->willReturn(false);
        $this->urlBuilderMock->method('getCurrentUrl')->willReturn('https://example.com/');
        $this->urlEncoderMock->method('encode')->willReturn('aHR0cHM6Ly9leGFtcGxlLmNvbS8=');
        $this->requestMock->method('isSecure')->willReturn(false);
        $this->urlBuilderMock->method('getUrl')
            ->with('checkout/cart/add', $this->isType('array'))
            ->willReturn('https://example.com/checkout/cart/add');

        $result = $this->block->getSubmitUrl();

        $this->assertSame('https://example.com/checkout/cart/add', $result);
    }

    /**
     * @covers Addtocart::getSubmitUrl()
     * @return void
     */
    public function testGetSubmitUrlAddsInCartParamOnCartPage(): void
    {
        /** @var Http|MockObject $httpRequestMock */
        $httpRequestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->getMock();
        $httpRequestMock->method('isSecure')->willReturn(false);
        $httpRequestMock->method('getRouteName')->willReturn('checkout');
        $httpRequestMock->method('getControllerName')->willReturn('cart');

        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $contextMock->method('getUrlBuilder')->willReturn($this->urlBuilderMock);
        $contextMock->method('getRequest')->willReturn($httpRequestMock);

        $block = new Addtocart(
            $contextMock,
            $this->urlEncoderMock,
            $this->nostoHelperAccountMock,
            $this->nostoHelperScopeMock,
            $this->nostoHelperDataMock
        );

        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getCode')->willReturn('default');
        $this->nostoHelperDataMock->method('getStoreCodeToUrl')->willReturn(false);
        $this->urlBuilderMock->method('getCurrentUrl')->willReturn('https://example.com/checkout/cart');
        $this->urlEncoderMock->method('encode')->willReturn('aHR0cHM6Ly9leGFtcGxlLmNvbS9jaGVja291dC9jYXJ0');

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with(
                'checkout/cart/add',
                $this->callback(function (array $params): bool {
                    return isset($params['in_cart']) && $params['in_cart'] === 1;
                })
            )
            ->willReturn('https://example.com/checkout/cart/add');

        $block->getSubmitUrl();
    }
}
