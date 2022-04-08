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

namespace Nosto\Tagging\Test\Unit\Helper;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Nosto\Request\Api\Token;
use Nosto\Tagging\Helper\Account;
use Magento\Framework\App\Helper\Context;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Nosto\Model\Signup\Account as SignupAccount;
use Magento\Store\Model\Store;
use Magento\Framework\Module\Manager;
use Nosto\Tagging\Logger\Logger;

class AccountTest extends TestCase
{
    /** @var Context|MockObject */
    protected $contextMock;

    /** @var WriterInterface|MockObject */
    protected $appConfig;

    /** @var NostoHelperScope|MockObject */
    protected $nostoHelperScope;

    /** @var NostoHelperUrl|MockObject */
    protected $nostoHelperUrl;

    /** @var UrlInterface|MockObject */
    protected $urlInterface;

    /** @var Logger */
    protected $loggerMock;

    /** @var MockObject */
    protected $moduleManagerMock;

    /** @var Account */
    protected $account;

    /**
     * SetUp test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->appConfig = $this->createMock(WriterInterface::class);
        $this->nostoHelperScope = $this->createMock(NostoHelperScope::class);
        $this->nostoHelperUrl = $this->createMock(NostoHelperUrl::class);
        $this->urlInterface = $this->createMock(UrlInterface::class);

        $this->loggerMock = $this->createMock(Logger::class);
        $this->moduleManagerMock = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()
            ->getMock();
        $this->contextMock->expects($this->any())->method('getModuleManager')->willReturn($this->moduleManagerMock);
        $this->contextMock->expects($this->any())->method('getLogger')->willReturn($this->loggerMock);

        $this->account = new Account($this->contextMock,
            $this->appConfig,
            $this->nostoHelperScope,
            $this->nostoHelperUrl,
            $this->urlInterface
        );
    }

    /**
     * @covers Account::saveAccount()
     * @return void
     */
    public function testSaveAccount()
    {
        $account = new SignupAccount('magento-test-account');
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
//        $tokens[] = new Token(Token::API_SSO, 'ssoToken');
//        $tokens[] = new Token(Token::API_PRODUCTS, 'productsToken');
//        $tokens[] = new Token(Token::API_EXCHANGE_RATES, 'ratesToken');
//        $tokens[] = new Token(Token::API_SETTINGS, 'settingsToken');
//        $tokens[] = new Token(Token::API_EMAIL, 'emailToken');
//        $account->setTokens($tokens);

        $result = $this->account->saveAccount($account, $store);
//        $accountName = $store->getConfig(Account::XML_PATH_ACCOUNT);
//        $savedTokens = $store->getConfig(Account::XML_PATH_TOKENS);
//        $savedDomain = $store->getConfig(Account::XML_PATH_DOMAIN);

        $this->assertTrue($result);
    }
}
