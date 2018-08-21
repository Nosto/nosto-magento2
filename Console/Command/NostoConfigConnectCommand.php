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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Module\ModuleListInterface;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Request\Api\Token;

class NostoConfigConnectCommand extends Command
{
    const NOSTO_ACCOUNT_ID = 'account-id';

    /**
     * @var NostoAccountHelper
     */
    private $accountHelper;

    private $nostoHelperScope;

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
                'Account ID to be reconnected'
            )->addOption(
                Token::API_SSO,
                null,
                InputOption::VALUE_REQUIRED,
                'SSO Token'
            )->addOption(
                Token::API_PRODUCTS,
                null,
                InputOption::VALUE_REQUIRED,
                'Products Token'
            )->addOption(
                Token::API_SETTINGS,
                null,
                InputOption::VALUE_REQUIRED,
                'Setting Token'
            )->addOption(
                Token::API_EXCHANGE_RATES,
                null,
                InputOption::VALUE_REQUIRED,
                'Rates Token'
            )->addOption(
                Token::API_EMAIL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Rates Token'
            )
        ;
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $accountId = $input->getOption(self::NOSTO_ACCOUNT_ID);

        $stores = $this->nostoHelperScope->getStores();
        foreach ($stores as $store) {
            $storeAccountId = $store->getConfig(NostoAccountHelper::XML_PATH_ACCOUNT);
            if ($storeAccountId === $accountId) {
                $account = $this->accountHelper->findAccount($store);
                $this->rewriteTokens($input, $account, $store);

            } else {
                $output->writeln('<info>Account not installed, cannot reconnect.<info>');
            }
        }
        $output->writeln('<info>Tokens Sucessfully Configured<info>');
    }

    private function rewriteTokens(InputInterface $input, NostoSignupAccount $account, $store)
    {
        $tokens = [
            new Token(Token::API_SSO, $input->getOption(Token::API_SSO)),
            new Token(Token::API_PRODUCTS, $input->getOption(Token::API_PRODUCTS)),
            new Token(Token::API_SETTINGS, $input->getOption(Token::API_SETTINGS)),
            new Token(Token::API_EXCHANGE_RATES, $input->getOption(Token::API_EXCHANGE_RATES))
        ];
        /* Email token is an optional argument */
        $emailToken = $input->getOption(Token::API_EMAIL);
        if ($emailToken) {
            $tokens[] = new Token(Token::API_EMAIL, $emailToken);
        }
        $account->setTokens($tokens);
        return $this->accountHelper->saveAccount($account, $store);
    }
}
