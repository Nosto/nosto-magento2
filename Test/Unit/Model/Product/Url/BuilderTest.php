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

namespace Nosto\Tagging\Test\Unit\Model\Product\Url;

use Magento\Catalog\Model\Product;
use Magento\Framework\Url as UrlFactory;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Model\Product\Url\Builder as UrlBuilder;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{

    public function testGetUrlInStore(): void
    {
        $product = $this->createMock(Product::class);
        $product->setId(1);
        $product->setData('request_path', 'test-product.html');

        $store = $this->createMock(Store::class);
        $store->setId(1);
        $store->setCode('default');

        $urlRewrite = $this->getMockBuilder(UrlRewrite::class)
            ->disableOriginalConstructor()
            ->getMock();
        $urlRewrite->method('getRequestPath')->willReturn('test-product.html');

        $urlFinderMock = $this->getMockBuilder(UrlFinderInterface::class)
            ->getMock();
        $urlFinderMock->method('findOneByData')->willReturn($urlRewrite);

        $nostoDataHelperMock = $this->getMockBuilder(NostoDataHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $nostoDataHelperMock->method('getStoreCodeToUrl')->with($store)->willReturn('http://localhost/');

        $urlFactoryMock = $this->getMockBuilder(UrlFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $urlFactoryMock->method('setScope')->with(1)->willReturnSelf();
//        $urlFactoryMock->method('setScope')->with(1)->willReturn('default');
        $urlFactoryMock->method('getUrl')->with('catalog/product/view', [
            'id' => 1,
            's' => 'test-product',
            '_nosid' => true,
            '_scope' => 'default',
            '_scope_to_url' => 'http://localhost/',
            '_query' => [],
        ])->willReturn('http://localhost/test-product.html');

        $urlBuilder = new UrlBuilder($urlFactoryMock, $urlFinderMock, $nostoDataHelperMock, []);

        // Test when rewrite exists
        $expectedResult = 'http://localhost/test-product.html';
        $result = $urlBuilder->getUrlInStore($product, $store);
        $this->assertEquals($expectedResult, $result);

        // Test when rewrite does not exist
        $urlFinderMock->method('findOneByData')->willReturn(null);

        $expectedResult = 'http://localhost/catalog/product/view/id/1/s/test-product';
        $result = $urlBuilder->getUrlInStore($product, $store);
        $this->assertEquals($expectedResult, $result);
    }
}