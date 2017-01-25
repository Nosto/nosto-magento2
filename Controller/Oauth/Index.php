<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Controller\Oauth;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Oauth\Builder as NostoOauthBuilder;
use NostoMessage;
use NostoOperationOauthSync;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    private $logger;
    private $backendUrlBuilder;
    private $nostoHelperAccount;
    private $oauthMetaBuilder;
    private $storeManager;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $backendUrlBuilder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoOauthBuilder $oauthMetaBuilder
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrlBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoOauthBuilder $oauthMetaBuilder
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->backendUrlBuilder = $backendUrlBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->oauthMetaBuilder = $oauthMetaBuilder;
    }

    /**
     * Handles the redirect from Nosto oauth2 authorization server when an
     * existing account is connected to a store.
     * This is handled in the front end as the oauth2 server validates the
     * "return_url" sent in the first step of the authorization cycle, and
     * requires it to be from the same domain that the account is configured
     * for and only redirects to that domain.
     *
     * @return void
     */
    public function execute()
    {
        $request = $this->getRequest();
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        if (($authCode = $request->getParam('code')) !== null) {
            try {
                $this->connectAccount($authCode, $store);
                $params = [
                    'message_type' => NostoMessage::TYPE_SUCCESS,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'store' => (int)$store->getId(),
                ];
            } catch (\Exception $e) {
                $this->logger->error($e, ['exception' => $e]);
                $params = [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'store' => (int)$store->getId(),
                ];
            }
            $this->redirectBackend('nosto/account/proxy', $params);
        } elseif (($error = $request->getParam('error')) !== null) {
            $logMsg = $error;
            if (($reason = $request->getParam('error_reason')) !== null) {
                $logMsg .= ' - ' . $reason;
            }
            if (($desc = $request->getParam('error_description')) !== null) {
                $logMsg .= ' - ' . $desc;
            }
            $this->logger->error($logMsg);
            $this->redirectBackend(
                'nosto/account/proxy',
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'message_text' => $desc,
                    'store' => (int)$store->getId(),
                ]
            );
        } else {
            /** @var \Magento\Framework\App\Response\Http $response */
            $response = $this->getResponse();
            $response->setHttpResponseCode(404);
        }
    }

    /**
     * Tries to connect the Nosto account and saves the account details to the
     * store config.
     *
     * @param string $authCode the OAuth authorization code by which to get the account details from Nosto.
     * @param Store $store the store the account is connect for.
     * @throws \Exception if the connection fails.
     */
    protected function connectAccount($authCode, $store)
    {
        $oldAccount = $this->nostoHelperAccount->findAccount($store);
        $meta = $this->oauthMetaBuilder->build($store, $oldAccount);
        $operation = new NostoOperationOauthSync($meta);
        $newAccount = $operation->exchange($authCode);

        // If we are updating an existing account,
        // double check that we got the same account back from Nosto.
        if (!is_null($oldAccount) && $newAccount->getName() !== $oldAccount->getName()) {
            throw new InputMismatchException(__('Failed to synchronise Nosto account details, account mismatch.'));
        }

        if (!$this->nostoHelperAccount->saveAccount($newAccount, $store)) {
            throw new CouldNotSaveException(__('Failed to save Nosto account.'));
        }
    }

    /**
     * Redirects the user to the Magento backend.
     *
     * @param string $path the backend path to redirect to.
     * @param array $args the url arguments.
     *
     * @return \Magento\Framework\App\ResponseInterface the response.
     */
    private function redirectBackend($path, $args = [])
    {
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();
        $response->setRedirect($this->backendUrlBuilder->getUrl($path, $args));
        return $response;
    }
}
