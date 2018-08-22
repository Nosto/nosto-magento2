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

namespace Nosto\Tagging\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\Module\ModuleListInterface;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Request\Api\Token;

class NostoConfigConnectCommand extends Command
{
    const NOSTO_ACCOUNT_ID = 'account-id';
    const TOKEN_SUFFIX = '_token';
    const SCOPE_CODE = 'scope-code';
    const OVERRIDE = 'override';
    const NO_EMAIL = 'no-email';

    /*
     * @var NostoAccountHelper
     */
    private $accountHelper;

    /**
     * @var NostoHelperScope
     */
    private $nostoHelperScope;

    /**
     * @var bool used to check the override flag
     */
    private $overrideOption = false;

    /**
     * NostoConfigConnectCommand constructor.
     * @param NostoAccountHelper $accountHelper
     */
    public function __construct(
        NostoAccountHelper $accountHelper,
        NostoHelperScope $nostoHelperScope
    ) {
        $this->accountHelper = $accountHelper;
        $this->nostoHelperScope = $nostoHelperScope;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('nosto:config:connect')
            ->setDescription('Reconnect Nosto Account Via CLI')
            ->addOption(
                self::NOSTO_ACCOUNT_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Nosto Account ID to be reconnected (Should exist already)'
            )->addOption(
                Token::API_SSO . self::TOKEN_SUFFIX,
                null,
                InputOption::VALUE_REQUIRED,
                'SSO token'
            )->addOption(
                Token::API_PRODUCTS . self::TOKEN_SUFFIX,
                null,
                InputOption::VALUE_REQUIRED,
                'Products Token'
            )->addOption(
                Token::API_SETTINGS . self::TOKEN_SUFFIX,
                null,
                InputOption::VALUE_REQUIRED,
                'Settings token'
            )->addOption(
                Token::API_EXCHANGE_RATES . self::TOKEN_SUFFIX,
                null,
                InputOption::VALUE_REQUIRED,
                'API exchange rates token'
            )->addOption(
                Token::API_EMAIL . self::TOKEN_SUFFIX,
                null,
                InputOption::VALUE_REQUIRED,
                'Email token'
            )->addOption(
                self::SCOPE_CODE,
                null,
                InputOption::VALUE_REQUIRED,
                'Store view code'
            )->addOption(
                self::OVERRIDE,
                'o',
                InputOption::VALUE_NONE,
                'Override tokens without asking for confirmation'
            )->addOption(
                self::NO_EMAIL,
                null,
                InputOption::VALUE_NONE,
                'Run script without asking for email token'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $this->overrideOption = $input->getOption(self::OVERRIDE) ?: false;
        $accountId = $input->getOption(self::NOSTO_ACCOUNT_ID) ?:
            $io->ask('Enter Nosto Account Id: ');
        $scopeCode = $input->getOption(self::SCOPE_CODE) ?:
            $io->ask('Enter Store Scope Code');

        $tokens = $this->getTokensFromInput($input, $io);
        if ($this->updateNostoTokens($tokens, $accountId, $io, $scopeCode)) {
            $io->success('Tokens Sucessfully Configured');
        } else {
            $io->error('Could not complete operation');
        }
    }

    /**
     * @param array $tokens
     * @param $accountId
     * @return bool
     */
    private function updateNostoTokens(array $tokens, $accountId, SymfonyStyle $io, $scopeCode)
    {
        $store = $this->getStoreByCode($scopeCode);
        if(!$store){
            $io->error('Store not found. Check your input.');
            return false;
        }
        $storeAccountId = $store->getConfig(NostoAccountHelper::XML_PATH_ACCOUNT);
        $account = $this->accountHelper->findAccount($store);
        if ($account && $storeAccountId === $accountId) {
            // Check for the override flag, if not present, ask.
            $this->overrideOption = $this->overrideOption ?:
                $io->confirm(
                    'Nosto account found for this store view. Override tokens?',
                    false
                );
            if ($this->overrideOption) {
                $account->setTokens($tokens);
                return $this->accountHelper->saveAccount($account, $store);
            }
        } else {
            $io->note('Local account not found, creating a new one...');
            $account = new NostoSignupAccount($accountId);
            $account->setTokens($tokens);
            return $this->accountHelper->saveAccount($account, $store);
        }
    }

    /**
     * @param $scopeCode the storeview code
     * @return \Magento\Store\Model\Store|null
     */
    private function getStoreByCode($scopeCode)
    {
        $stores = $this->nostoHelperScope->getStores();
        foreach ($stores as $store) {
            if ($store->getCode() === $scopeCode) {
                return $store;
            }
        }
    }

    /**
     * Check if required arguments passed by command line are present,
     * if not, will ask for the remaining parameters.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array of Token objects
     * @throws \Nosto\NostoException
     */
    private function getTokensFromInput(InputInterface $input, SymfonyStyle $io)
    {
        $tokens = array();

        $ssoToken = $input->getOption(Token::API_SSO . self::TOKEN_SUFFIX) ?:
            $io->ask('Enter SSO Token: ');
        $tokens[] = new Token(Token::API_SSO, $ssoToken);

        $productsToken = $input->getOption(Token::API_PRODUCTS . self::TOKEN_SUFFIX) ?:
            $io->ask('Enter Products Token: ');
        $tokens[] = new Token(Token::API_PRODUCTS, $productsToken);

        $ratesToken = $input->getOption(Token::API_EXCHANGE_RATES . self::TOKEN_SUFFIX) ?:
            $io->ask('Enter Exchange Rates Token: ');
        $tokens[] = new Token(Token::API_EXCHANGE_RATES, $ratesToken);

        $settingsToken = $input->getOption(Token::API_SETTINGS . self::TOKEN_SUFFIX) ?:
            $io->ask('Enter Settings Token: ');
        $tokens[] = new Token(Token::API_SETTINGS, $settingsToken);

        $emailToken = $input->getOption(Token::API_EMAIL . self::TOKEN_SUFFIX);
        $noEmailFlag = $input->getOption(self::NO_EMAIL);
        if (!$emailToken && !$noEmailFlag) {
            $emailToken = $io->ask(
                'Enter Email Token (Optional): ',
                false
            );
        }
        if ($emailToken) {
            $tokens[] = new Token(Token::API_EMAIL, $emailToken);
        }
        return $tokens;
    }
}
