<?php
/**
 * Copyright (c) 2022, Nosto Solutions Ltd
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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NotFoundException;
use Nosto\Mixins\ConnectionTrait;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Meta\Account\Connection\Builder as NostoConnectionMetadataBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;

class Open extends Base
{
    use ConnectionTrait;

    private Session $backendAuthSession;
    private NostoHelperAccount $nostoHelperAccount;
    private NostoHelperScope $nostoHelperScope;
    private NostoConnectionMetadataBuilder $connectionMetadataBuilder;
    private NostoCurrentUserBuilder $currentUserBuilder;
    private NostoLogger $logger;

    /**
     * @param Context $context
     * @param Session $backendAuthSession
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoConnectionMetadataBuilder $connectionMetadataBuilder
     * @param NostoCurrentUserBuilder $currentUserBuilder
     * @param NostoLogger $logger
     */
    public function __construct(
        Context $context,
        Session $backendAuthSession,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoConnectionMetadataBuilder $connectionMetadataBuilder,
        NostoCurrentUserBuilder $currentUserBuilder,
        NostoLogger $logger
    ) {
        parent::__construct($context);

        $this->backendAuthSession = $backendAuthSession;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->connectionMetadataBuilder = $connectionMetadataBuilder;
        $this->currentUserBuilder = $currentUserBuilder;
        $this->logger = $logger;
    }

    /**
     * @return Redirect
     * @suppress PhanUndeclaredMethod
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            return $resultRedirect->setUrl($this->getNostoUrl());
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
            $this->getMessageManager()->addErrorMessage(
            /** @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal */
                __("Something went wrong when opening Nosto. Please see logs for more details")
            );
            return $resultRedirect->setUrl($this->getUrl(
                '*/*/',
                ['store' => $this->nostoHelperScope->getStore()->getId()]
            ));
        }
    }

    /**
     * Gets the Nosto url for the account settings page from Nosto.
     * If there is an account for the current store and the admin user can be
     * logged in to that account using SSO, the url will be for the account
     * management. In other cases, the url will be that of the install screen
     * where a new Nosto account can be created.
     *
     * @return string the Nosto url or empty string if it cannot be created.
     * @throws NotFoundException
     */
    public function getNostoUrl()
    {
        $params = [];
        $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
        $get = ['store' => $store->getId()];
        $params['createUrl'] = $this->getUrl('*/*/create', $get);
        $params['deleteUrl'] = $this->getUrl('*/*/delete', $get);
        $params['connectUrl'] = $this->getUrl('*/*/connect', $get);
        $params['dashboard_rd'] = "true";

        // Pass any error/success messages we might have to the controls.
        // These can be available when getting redirect back from the OAuth
        // front controller after connecting a Nosto account to a store.
        $nostoMessage = $this->backendAuthSession->getData('nosto_message');
        if (is_array($nostoMessage) && !empty($nostoMessage)) {
            foreach ($nostoMessage as $key => $value) {
                if (is_string($key) && !empty($value)) {
                    $params[$key] = $value;
                }
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->backendAuthSession->setData('nosto_message', null);
        }

        return $this->buildURL($params);
    }

    /**
     * @inheritDoc
     */
    public function getConnectionMetadata()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->connectionMetadataBuilder->build($store);
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getUser()
    {
        return $this->currentUserBuilder->build();
    }

    /**
     * @inheritDoc
     */
    public function getAccount()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->nostoHelperAccount->findAccount($store);
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return null;
    }
}
