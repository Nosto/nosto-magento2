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

namespace Nosto\Tagging\Controller\Oauth;

use Exception;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Store\Model\StoreRepository;
use Nosto\Mixins\OauthTrait;
use Nosto\OAuth;
use Nosto\Tagging\Helper\Cache as NostoHelperCache;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Meta\Oauth\Builder as NostoOauthBuilder;
use Nosto\Types\Signup\AccountInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\NostoException;

class Index extends Action
{
    use OauthTrait;
    private $logger;
    private $urlBuilder;
    private $nostoHelperAccount;
    private $oauthMetaBuilder;
    private $nostoHelperScope;
    private $nostoHelperCache;
    private $storeRepository;

    /**
     * @param Context $context
     * @param NostoLogger $logger
     * @param NostoHelperScope $nostoHelperScope
     * @param UrlInterface $urlBuilder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperCache $nostoHelperCache
     * @param NostoOauthBuilder $oauthMetaBuilder
     * @param StoreRepository $storeRepository
     */
    public function __construct(
        Context $context,
        NostoLogger $logger,
        NostoHelperScope $nostoHelperScope,
        UrlInterface $urlBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperCache $nostoHelperCache,
        NostoOauthBuilder $oauthMetaBuilder,
        StoreRepository $storeRepository
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->oauthMetaBuilder = $oauthMetaBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperCache = $nostoHelperCache;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Handles the redirect from Nosto oauth2 authorization server when an existing account is
     * connected to a store. This is handled in the front end as the oauth2 server validates the
     * "return_url" sent in the first step of the authorization cycle, and requires it to be from
     * the same domain that the account is configured for and only redirects to that domain.
     *
     * @return void
     */
    public function execute()
    {
        $this->connect();
    }

    /**
     * Implemented trait method that is responsible for fetching the OAuth parameters used for all
     * OAuth operations
     *
     * @return Oauth the OAuth parameters for the operations
     */
    public function getMeta()
    {
        $account = $this->nostoHelperAccount->findAccount($this->nostoHelperScope->getStore());
        return $this->oauthMetaBuilder->build($this->nostoHelperScope->getStore(), $account);
    }

    /**
     * Implemented trait method that is responsible for saving an account with the all tokens for
     * the current store view (as defined by the parameter.)
     *
     * @param AccountInterface $account the account to save
     * @return boolean a boolean value indicating whether the account was saved
     * @throws NostoException
     * @suppress PhanTypeMismatchArgument
     */
    public function save(AccountInterface $account)
    {
        $stores = $this->storeRepository->getList();
        $currentStore = $this->nostoHelperScope->getStore();
        /** @var \Magento\Store\Model\Store $store */
        foreach ($stores as $store) {
            $existingAccount = $this->nostoHelperAccount->findAccount($store);
            if ($existingAccount !== null
                && $existingAccount->getName() === $account->getName()
                && $currentStore->getId() !== $store->getId()
            ) {
                throw new NostoException(
                    sprintf(
                        'This account is already being used by "%s". 
                                Please create a new account for each store view',
                        $store->getName()
                    )
                );
            }
        }

        $success =  $this->nostoHelperAccount->saveAccount(
            $account,
            $this->nostoHelperScope->getStore()
        );

        // Invalidate cache after reconnected nosto account
        if ($success) {
            $this->nostoHelperCache->invalidatePageCache();
            $this->nostoHelperCache->invalidateLayoutCache();
        }

        return $success;
    }

    /**
     * Implemented trait method that redirects the user with the authentication params to the
     * admin controller.
     *
     * @param array $params the parameters to be used when building the redirect
     */
    public function redirect(array $params)
    {
        $response = $this->getResponse();
        if ($response instanceof Http) {
            $params['store'] = (int)$this->nostoHelperScope->getStore()->getId();
            $response->setRedirect($this->urlBuilder->getUrl('nosto/account/proxy', $params));
        }
    }

    /**
     * Implemented trait method that is a utility responsible for fetching a specified query
     * parameter from the GET request.
     *
     * @param string $name the name of the query parameter to fetch
     * @return string the value of the specified query parameter
     */
    public function getParam($name)
    {
        return $this->getRequest()->getParam($name);
    }

    /**
     * Implemented trait method that is responsible for logging an exception to the Magento error
     * log when an error occurs.
     *
     * @param Exception $e the exception to be logged
     */
    public function logError(Exception $e)
    {
        $this->logger->exception($e);
    }

    /**
     * Implemented trait method that is responsible for redirecting the user to a 404 page when
     * the authorization code is invalid.
     */
    public function notFound()
    {
        $response = $this->getResponse();
        if ($response instanceof Http) {
            $response->setHttpResponseCode(404);
        }
    }
}
