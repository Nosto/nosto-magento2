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

namespace Nosto\Tagging\Block\Adminhtml\Account;

use Magento\Backend\Block\Template as BlockTemplate;
use Magento\Backend\Block\Template\Context as BlockContext;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\NotFoundException;
use Magento\Store\Model\Store;
use Nosto\Helper\IframeHelper;
use Nosto\Nosto;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Store as NostoHelperStore;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\Meta\Account\Sso\Builder as NostoSsoBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;

/**
 * Iframe block for displaying the Nosto account management iframe.
 * This iframe is used to setup and manage your Nosto accounts on a store basis
 * in Magento.
 */
class Iframe extends BlockTemplate
{
    /**
     * Default iframe origin regexp for validating window.postMessage() calls.
     */
    const DEFAULT_IFRAME_ORIGIN_REGEXP = '(https:\/\/(.*)\.hub\.nosto\.com)|(https:\/\/my\.nosto\.com)';
    private $nostoHelperAccount;
    private $backendAuthSession;
    private $nostoSsoBuilder;
    private $nostoIframeMetaBuilder;
    private $nostoCurrentUserBuilder;
    private $nostoHelperStore;

    /**
     * Constructor.
     *
     * @param BlockContext $context the context.
     * @param NostoHelperAccount $nostoHelperAccount the account helper.
     * @param Session $backendAuthSession
     * @param NostoSsoBuilder $nostoSsoBuilder
     * @param NostoIframeMetaBuilder $iframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param NostoHelperStore $nostoHelperStore
     * @param array $data
     */
    public function __construct(
        BlockContext $context,
        NostoHelperAccount $nostoHelperAccount,
        Session $backendAuthSession,
        NostoSsoBuilder $nostoSsoBuilder,
        NostoIframeMetaBuilder $iframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        NostoHelperStore $nostoHelperStore,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->backendAuthSession = $backendAuthSession;
        $this->nostoSsoBuilder = $nostoSsoBuilder;
        $this->nostoIframeMetaBuilder = $iframeMetaBuilder;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
        $this->nostoHelperStore = $nostoHelperStore;
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

        $store = $this->getSelectedStore();
        $account = $this->nostoHelperAccount->findAccount($store);
        return IframeHelper::getUrl(
            $this->nostoIframeMetaBuilder->build($store),
            $account,
            $this->nostoCurrentUserBuilder->build(),
            $params
        );
    }

    /**
     * Returns the currently selected store.
     * Nosto can only be configured on a store basis, and if we cannot find a
     * store, an exception is thrown.
     *
     * @return Store the store.
     * @throws NotFoundException store not found.
     */
    public function getSelectedStore()
    {
        $store = null;
        if ($this->nostoHelperStore->isSingleStoreMode()) {
            $store = $this->nostoHelperStore->getStore(true);
        } elseif (($storeId = $this->_request->getParam('store'))) {
            $store = $this->nostoHelperStore->getStore($storeId);
        } elseif (($this->nostoHelperStore->getStore())) {
            $store = $this->nostoHelperStore->getStore();
        } else {
            throw new NotFoundException(__('Store not found.'));
        }

        return $store;
    }

    /**
     * Returns the config for the Nosto iframe JS component.
     * This config can be converted into JSON in the view file.
     *
     * @return array the config.
     */
    public function getIframeConfig()
    {
        $get = [
            'store' => $this->getSelectedStore()->getId(),
            'isAjax' => true
        ];
        return [
            'iframe_handler' => [
                'origin' => $this->getIframeOrigin(),
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
     * Returns the valid origin url regexp from where the iframe should accept
     * postMessage calls.
     * This is configurable to support different origins based on $_ENV.
     *
     * @return string the origin url regexp.
     */
    public function getIframeOrigin()
    {
        return Nosto::getEnvVariable(
            'NOSTO_IFRAME_ORIGIN_REGEXP',
            self::DEFAULT_IFRAME_ORIGIN_REGEXP
        );
    }
}
