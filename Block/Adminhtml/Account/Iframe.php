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

namespace Nosto\Tagging\Block\Adminhtml\Account;

use Magento\Backend\Block\Template as BlockTemplate;
use Magento\Backend\Block\Template\Context as BlockContext;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\NotFoundException;
use Magento\Store\Model\Store;
use Nosto\Mixins\IframeTrait;
use Nosto\Nosto;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;

/**
 * Iframe block for displaying the Nosto account management iframe.
 * This iframe is used to setup and manage your Nosto accounts on a store basis
 * in Magento.
 */
class Iframe extends BlockTemplate
{
    use IframeTrait;
    const IFRAME_VERSION = 1;

    /**
     * Default iframe origin regexp for validating window.postMessage() calls.
     */
    const DEFAULT_IFRAME_ORIGIN_REGEXP = '(https:\/\/(.*)\.hub\.nosto\.com)|(https:\/\/my\.nosto\.com)';
    private $nostoHelperAccount;
    private $backendAuthSession;
    private $nostoIframeMetaBuilder;
    private $nostoCurrentUserBuilder;
    private $nostoHelperScope;
    private $logger;

    /**
     * Constructor.
     *
     * @param BlockContext $context the context.
     * @param NostoHelperAccount $nostoHelperAccount the account helper.
     * @param Session $backendAuthSession
     * @param NostoIframeMetaBuilder $iframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param array $data
     */
    public function __construct(
        BlockContext $context,
        NostoHelperAccount $nostoHelperAccount,
        Session $backendAuthSession,
        NostoIframeMetaBuilder $iframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->backendAuthSession = $backendAuthSession;
        $this->nostoIframeMetaBuilder = $iframeMetaBuilder;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->logger = $logger;
    }

    /**
     * Gets the iframe url for the account settings page from Nosto.
     * If there is an account for the current store and the admin user can be
     * logged in to that account using SSO, the url will be for the account
     * management. In other cases, the url will be that of the install screen
     * where a new Nosto account can be created.
     *
     * @return string the iframe url or empty string if it cannot be created.
     */
    public function getIframeUrl()
    {
        $params = [];
        $params['v'] = self::IFRAME_VERSION;

        // Pass any error/success messages we might have to the iframe.
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

        $url = $this->buildURL($params);

        return $url;
    }

    /**
     * Returns the config for the Nosto iframe JS component.
     * This config can be converted into JSON in the view file.
     *
     * @return array the config.
     * @throws NotFoundException
     */
    public function getIframeConfig()
    {
        $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
        $get = ['store' => $store->getId(), 'isAjax' => true];
        return [
            'iframe_handler' => [
                'origin' => Nosto::getIframeOriginRegex(),
                'xhrParams' => [
                    'form_key' => $this->formKey->getFormKey()
                ],
                'urls' => [
                    'createAccount' => $this->getUrl('*/*/create', $get),
                    'connectAccount' => $this->getUrl('*/*/connect', $get),
                    'syncAccount' => $this->getUrl('*/*/sync', $get),
                    'deleteAccount' => $this->getUrl('*/*/delete', $get)
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getIframe()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->nostoIframeMetaBuilder->build($store);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getUser()
    {
        return $this->nostoCurrentUserBuilder->build();
    }

    /**
     * @inheritdoc
     */
    public function getAccount()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->nostoHelperAccount->findAccount($store);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return null;
    }
}
