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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\NostoException;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Tagging\Helper\Account;
use Nosto\Model\Signup\Account as SignupAccount;
use Nosto\Request\Api\Token as NostoToken;
use Nosto\Tagging\Model\MockOperation\MockUpsertProduct;

class Submit implements ActionInterface
{
    /** @var RequestInterface $request */
    private RequestInterface $request;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var StoreManagerInterface $storeManager */
    private StoreManagerInterface $storeManager;

    /** @var Account $accountHelper */
    private Account $accountHelper;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var CookieManagerInterface $cookieManager */
    private CookieManagerInterface $cookieManager;

    /** @var CookieMetadataFactory $cookieMetadataFactory */
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * Submit constructor
     *
     * @param ManagerInterface $messageManager
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param Account $accountHelper
     * @param RedirectFactory $redirectFactory
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        ManagerInterface $messageManager,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Account $accountHelper,
        RedirectFactory $redirectFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->accountHelper = $accountHelper;
        $this->redirectFactory = $redirectFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Process login in Nosto debugger
     *
     * @throws NostoException
     * @throws NoSuchEntityException
     * @throws AbstractHttpException
     * @throws Exception
     */
    public function execute(): Redirect
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $account = $this->accountHelper->findAccount($store);
        $token = $this->request->getParam('token');
        if (false === $this->sendApiCallForToken($account->getName(), $token)['success']) {
            $this
                ->messageManager
                ->addErrorMessage(__($this->sendApiCallForToken($account->getName(), $token)['message']));

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

        $this->setNostoDebuggerCookie($token);

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
    }

    /**
     * Send API request to Nosto
     *
     * @throws NostoException
     * @throws AbstractHttpException
     */
    private function sendApiCallForToken(string $accountName, string $token): array
    {
        $signupAccount = new SignupAccount($accountName);
        $signupAccount->addApiToken(new NostoToken(NostoToken::API_PRODUCTS, $token));

        return (new MockUpsertProduct($signupAccount))->upsert();
    }

    /**
     * @throws FailureToSendException
     * @throws InputException
     * @throws CookieSizeLimitReachedException
     */
    private function setNostoDebuggerCookie(string $token): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath('/')
            ->setHttpOnly(true)
            ->setSecure(false);
        $this->cookieManager->setPublicCookie('nosto_debugger_cookie', $token, $metadata);
    }
}
