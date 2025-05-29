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

namespace Nosto\Tagging\Controller\Monitoring;

use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Tagging\Helper\Cache;

class Logout implements ActionInterface
{
    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var Cache $cache */
    private Cache $cache;

    /** @var CookieManagerInterface $cookieManager */
    private CookieManagerInterface $cookieManager;

    /** @var CookieMetadataFactory $cookieMetadataFactory */
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * Logout constructor
     *
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param Cache $cache
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        Cache $cache,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->cache = $cache;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Logout user
     *
     * @return Redirect
     * @throws Exception
     */
    public function execute(): Redirect
    {
        $this->deleteNostoDebuggerCookie();

        $this->cache->flushCache();

        $this->messageManager->addSuccessMessage(__('You have been logged out.'));

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
    }

    /**
     * @throws FailureToSendException
     * @throws InputException
     */
    private function deleteNostoDebuggerCookie(): void
    {
        $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath('/')
            ->setDomain('')
            ->setDuration(-3600)
            ->setHttpOnly(true)
            ->setSecure(false);
        $this->cookieManager->deleteCookie('nosto_debugger_cookie', $cookieMetadata);
    }
}