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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AddtocartTest extends TestCase
{
    /** @var Context&Stub */
    private $contextMock;

    /** @var EncoderInterface&Stub */
    private $urlEncoderMock;

    /** @var NostoHelperAccount&Stub */
    private $nostoHelperAccountMock;

    /** @var NostoHelperScope&Stub */
    private $nostoHelperScopeMock;

    /** @var NostoHelperData&Stub */
    private $nostoHelperDataMock;

    /** @var UrlInterface&Stub */
    private $urlBuilderMock;

    /** @var RequestInterface&Stub */
    private $requestMock;

    /** @var Store&Stub */
    private $storeMock;

    private ?Addtocart $block = null;

    protected function setUp(): void
    {
        $this->urlBuilderMock = $this->createStub(UrlInterface::class);
        $this->requestMock = $this->createStub(RequestInterface::class);
        $this->storeMock = $this->createStub(Store::class);

        $this->contextMock = $this->createStub(Context::class);

        $this->urlEncoderMock = $this->createStub(EncoderInterface::class);
        $this->nostoHelperAccountMock = $this->createStub(NostoHelperAccount::class);
        $this->nostoHelperScopeMock = $this->createStub(NostoHelperScope::class);
        $this->nostoHelperDataMock = $this->createStub(NostoHelperData::class);
    }

    private function getAddtocart(): Addtocart
    {
        $this->contextMock->method('getUrlBuilder')->willReturn($this->urlBuilderMock);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        return $this->block ??= new Addtocart(
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
        $this->assertNull($this->getAddtocart()->getAbstractObject());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsTrueWhenEnabled(): void
    {
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('isHyvaEnabled')
            ->with($this->storeMock)
            ->willReturn(true);

        $this->assertTrue($this->getAddtocart()->isHyva());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsFalseWhenDisabled(): void
    {
        $this->nostoHelperScopeMock = $this->createMock(NostoHelperScope::class);

        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->nostoHelperScopeMock->expects($this->atLeastOnce())->method('isHyvaEnabled')
            ->with($this->storeMock)
            ->willReturn(false);

        $this->assertFalse($this->getAddtocart()->isHyva());
    }

    /**
     * @covers Addtocart::isHyva()
     * @return void
     */
    public function testIsHyvaReturnsFalseOnException(): void
    {
        $this->nostoHelperScopeMock->method('getStore')
            ->willThrowException(new \Exception('Store not found'));

        $this->assertFalse($this->getAddtocart()->isHyva());
    }

    /**
     * @covers Addtocart::getSubmitUrl()
     * @return void
     */
    public function testGetSubmitUrl(): void
    {
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->nostoHelperDataMock = $this->createMock(NostoHelperData::class);

        $this->nostoHelperScopeMock->method('getStore')->willReturn($this->storeMock);
        $this->storeMock->method('getCode')->willReturn('default');
        $this->nostoHelperDataMock->expects($this->atLeastOnce())->method('getStoreCodeToUrl')
            ->with($this->storeMock)
            ->willReturn(false);
        $this->urlBuilderMock->method('getCurrentUrl')->willReturn('https://example.com/');
        $this->urlEncoderMock->method('encode')->willReturn('aHR0cHM6Ly9leGFtcGxlLmNvbS8=');
        $this->requestMock->method('isSecure')->willReturn(false);
        $this->urlBuilderMock->expects($this->atLeastOnce())->method('getUrl')
            ->with('checkout/cart/add', $this->isType('array'))
            ->willReturn('https://example.com/checkout/cart/add');

        $result = $this->getAddtocart()->getSubmitUrl();

        $this->assertSame('https://example.com/checkout/cart/add', $result);
    }

    /**
     * @covers Addtocart::getSubmitUrl()
     * @return void
     */
    public function testGetSubmitUrlAddsInCartParamOnCartPage(): void
    {
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        /** @var Http|Stub $httpRequestMock */
        $httpRequestMock = $this->createStub(Http::class);
        $httpRequestMock->method('isSecure')->willReturn(false);
        $httpRequestMock->method('getRouteName')->willReturn('checkout');
        $httpRequestMock->method('getControllerName')->willReturn('cart');

        $contextMock = $this->createStub(Context::class);
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
