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

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Request\Api\Token;
use Nosto\Tagging\Helper\Data as NostoHelper;
use Nosto\Types\Signup\AccountInterface;

/**
 * NostoHelperAccount helper class for common tasks related to Nosto accounts.
 * Everything related to saving/updating/deleting accounts happens in here.
 */
class Account extends AbstractHelper
{
    /**
     * Path to store config nosto account name.
     */
    const XML_PATH_ACCOUNT = 'nosto_tagging/settings/account';

    /**
     * Path to store config nosto account tokens.
     */
    const XML_PATH_TOKENS = 'nosto_tagging/settings/tokens';

    /**
     * Platform UI version
     */
    const IFRAME_VERSION = 0;

    private $config;
    private $moduleManager;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param WriterInterface $appConfig the app config writer.
     */
    public function __construct(
        Context $context,
        WriterInterface $appConfig
    ) {
        parent::__construct($context);

        $this->config = $appConfig;
        $this->moduleManager = $context->getModuleManager();
    }

    /**
     * Saves the account and the associated api tokens for the store.
     *
     * @param AccountInterface $account the account to save.
     * @param Store $store the store.
     * @return bool true on success, false otherwise.
     */
    public function saveAccount(AccountInterface $account, Store $store)
    {
        if ((int)$store->getId() < 1) {
            return false;
        }

        $tokens = [];
        foreach ($account->getTokens() as $token) {
            $tokens[$token->getName()] = $token->getValue();
        }

        $this->config->save(
            self::XML_PATH_ACCOUNT,
            $account->getName(),
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $this->config->save(
            self::XML_PATH_TOKENS,
            json_encode($tokens),
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        $store->resetConfig();

        return true;
    }

    /**
     * Removes an account with associated api tokens for the store.
     *
     * @param Store $store the store.
     * @return bool true on success, false otherwise.
     */
    public function deleteAccount(Store $store) {
        if ((int)$store->getId() < 1) {
            return false;
        }

        $this->config->delete(
            self::XML_PATH_ACCOUNT,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $this->config->delete(
            self::XML_PATH_TOKENS,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        $store->resetConfig();

        return true;
    }

    /**
     * Checks if Nosto module is enabled and Nosto account is set
     *
     * @param Store $store
     * @return bool
     */
    public function nostoInstalledAndEnabled(Store $store)
    {
        $enabled = false;
        if ($this->moduleManager->isEnabled(NostoHelper::MODULE_NAME)) {
            if ($this->findAccount($store)) {
                $enabled = true;
            }
        }

        return $enabled;
    }

    /**
     * Returns the account with associated api tokens for the store.
     *
     * @param Store $store the store.
     * @return NostoSignupAccount|null the account or null if not found.
     */
    public function findAccount(Store $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $accountName = $store->getConfig(self::XML_PATH_ACCOUNT);

        if (!empty($accountName)) {
            $account = new NostoSignupAccount($accountName);
            /** @noinspection PhpUndefinedMethodInspection */
            $tokens = json_decode(
                $store->getConfig(self::XML_PATH_TOKENS),
                true
            );
            if (is_array($tokens) && !empty($tokens)) {
                foreach ($tokens as $name => $value) {
                    $account->addApiToken(new Token($name, $value));
                }
            }
            $missingTokens = false;
            foreach ($this->forgeMissingApiTokens($account) as $token) {
                $account->addApiToken($token);
                $missingTokens = true;
            }
            if ($missingTokens) {
                $this->saveAccount($account, $store);
            }

            return $account;
        }

        return null;
    }

    /**
     * Creates tokens for settings and rates if those are missing
     *
     * @param AccountInterface $account
     * @return Token[]
     */
    private function forgeMissingApiTokens(AccountInterface $account)
    {
        $tokens = [];
        $ssoToken = $account->getApiToken(Token::API_SSO);
        if ($ssoToken instanceof Token) {
            if (!$account->getApiToken(Token::API_EXCHANGE_RATES)) {
                $ratesToken = new Token(
                    Token::API_EXCHANGE_RATES,
                    $ssoToken->getValue()
                );
                $tokens[] = $ratesToken;
            }
            if (!$account->getApiToken(Token::API_SETTINGS)) {
                $settingsToken = new Token(
                    Token::API_SETTINGS,
                    $ssoToken->getValue()
                );
                $tokens[] = $settingsToken;
            }
        }

        return $tokens;
    }
}
