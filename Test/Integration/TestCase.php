<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Test\Integration;

use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Nosto\Tagging\Helper\Account;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Base class for Nosto integration tests.
 * Contains common utility methods to create mocks etc.
*/
abstract class TestCase extends PhpUnitTestCase
{
    private $initialized = false;

    /**
     * @return ObjectManagerInterface
     */
    protected function getObjectManager()
    {
        return Bootstrap::getObjectManager();
    }

    protected function setConfig($path, $value)
    {
        $this->getObjectManager()->get('Magento\Framework\App\Config\MutableScopeConfigInterface')->setValue(
            $path,
            $value,
            ScopeInterface::SCOPE_STORE,
            'default'
        );
    }

    public function setUp()
    {
        $this->init();
    }

    public function tearDown()
    {
        //ToDo - clean up
    }

    private function init()
    {
        if ($this->initialized) {
            return;
        }

        $this->setConfig(Account::XML_PATH_ACCOUNT, 'test-account');

        //ToDo - setup basic Nosto config like accounts etc.
        $this->initialized = true;
    }
}