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

namespace Nosto\Tagging\Helper;

use Exception;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\User;
use Nosto\Operation\UninstallAccount;
use Nosto\Request\Api\Token;
use Nosto\Tagging\Helper\Data as NostoHelper;
use Nosto\Types\Signup\AccountInterface;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;

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
     * Path to store config store domain.
     */
    const XML_PATH_DOMAIN = 'nosto_tagging/settings/domain';

    /**
     * Platform UI version
     */
    const IFRAME_VERSION = 0;
    private $config;
    private $moduleManager;
    private $logger;
    private $nostoHelperScope;
    private $nostoHelperUrl;
    private $urlBuilder;

    /**
     * Account constructor.
     * @param Context $context
     * @param WriterInterface $appConfig
     * @param Scope $nostoHelperScope
     * @param Url $nostoHelperUrl
     */
    public function __construct(
        Context $context,
        WriterInterface $appConfig,
        NostoHelperScope $nostoHelperScope,
        NostoHelperUrl $nostoHelperUrl
    ) {
        parent::__construct($context);

        $this->config = $appConfig;
        $this->moduleManager = $context->getModuleManager();
        $this->logger = $context->getLogger();
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->urlBuilder = $context->getUrlBuilder();
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
        $this->config->save(
            self::XML_PATH_DOMAIN,
            $this->nostoHelperUrl->getActiveDomain($store),
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        $store->resetConfig();

        return true;
    }

    /**
     * Removes an account with associated api tokens for the store.
     *
     * @param NostoSignupAccount $account the account to remove.
     * @param Store $store the store.
     * @param User $currentUser
     * @return bool true on success, false otherwise.
     */
    public function deleteAccount(
        NostoSignupAccount $account,
        Store $store,
        User $currentUser
    ) {
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
        $this->config->delete(
            self::XML_PATH_DOMAIN,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        try {
            // Notify Nosto that the account was deleted.
            $service = new UninstallAccount($account);
            $service->delete($currentUser);
        } catch (\Exception $e) {
            $this->logger->error($e->__toString());
        }

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
        return $this->moduleManager->isEnabled(NostoHelper::MODULE_NAME)
            && $this->findAccount($store);
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

        if ($accountName !== null) {
            $account = new NostoSignupAccount($accountName);
            /** @noinspection PhpUndefinedMethodInspection */
            $tokens = json_decode(
                $store->getConfig(self::XML_PATH_TOKENS),
                true
            );
            if (is_array($tokens) && !empty($tokens)) {
                foreach ($tokens as $name => $value) {
                    try {
                        $account->addApiToken(new Token($name, $value));
                    } catch (Exception $e) {
                        $this->logger->error($e->__toString());
                    }
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
                try {
                    $ratesToken = new Token(
                        Token::API_EXCHANGE_RATES,
                        $ssoToken->getValue()
                    );
                    $tokens[] = $ratesToken;
                } catch (NostoException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
            if (!$account->getApiToken(Token::API_SETTINGS)) {
                try {
                    $settingsToken = new Token(
                        Token::API_SETTINGS,
                        $ssoToken->getValue()
                    );
                    $tokens[] = $settingsToken;
                } catch (NostoException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        return $tokens;
    }

    /**
     * Returns an array of stores where Nosto is installed
     *
     * @return Store[]
     */
    public function getStoresWithNosto()
    {
        $stores = $this->nostoHelperScope->getStores();
        $storesWithNosto = [];
        foreach ($stores as $store) {
            $nostoAccount = $this->findAccount($store);
            if ($nostoAccount instanceof NostoSignupAccount) {
                $storesWithNosto[] = $store;
            }
        }

        return $storesWithNosto;
    }

    /**
     * Returns the stored storefront domain
     *
     * @param $store
     * @return string the domain
     */
    public function getStoreFrontDomain($store)
    {
        return $store->getConfig(self::XML_PATH_DOMAIN);
    }

    /**
     * Returns the Nosto account name for the store
     *
     * @param $store
     * @return string account name
     */
    public function getAccountName($store)
    {
        return $store->getConfig(self::XML_PATH_ACCOUNT);
    }

    /**
     * Returns bool value that represent validity of domain
     *
     * @param Store $store
     * @return bool
     */
    public function isDomainValid(Store $store)
    {
        $storedDomain = $this->getStoreFrontDomain($store);
        $realDomain = $this->nostoHelperUrl->getActiveDomain($store);
        return ($realDomain === $storedDomain);
    }

    /**
     * Returns the list of invalid Nosto accounts
     *
     * @return array
     */
    public function getInvalidAccounts()
    {
        $stores = $this->getStoresWithNosto();
        $invalidAccounts = [];

        foreach ($stores as $store) {
            if (!$this->isDomainValid($store)) {
                $invalidAccounts[] = [
                    'storeName' => $store->getName(),
                    'nostoAccount' => $this->getAccountName($store),
                    'currentDomain' => $this->nostoHelperUrl->getActiveDomain($store),
                    'storedDomain' => $this->getStoreFrontDomain($store),
                    'resetUrl' => $this->urlBuilder->getUrl('nosto/account/index', ['store' => $store->getId()])
                ];
            }
        }
        return $invalidAccounts;
    }
}
