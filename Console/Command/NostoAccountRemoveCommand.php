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
use Symfony\Component\Console\Style\SymfonyStyle;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Scope as NostoScopeHelper;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Framework\App\Config\Storage\WriterInterface;

class NostoAccountRemoveCommand extends Command
{
    /**
     * Path to store config nosto account name.
     */
    const XML_PATH_ACCOUNT = 'nosto_tagging/settings/account';

    /**
     * Path to store config nosto account tokens.
     */
    const XML_PATH_TOKENS = 'nosto_tagging/settings/tokens';
    const NOSTO_ACCOUNT_ID = 'account-id';
    const TOKEN_SUFFIX = '_token';
    const SCOPE_CODE = 'scope-code';

    /*
    * @var NostoAccountHelper
    */
    private $nostoAccountHelper;

    /**
     * @var NostoHelperScope
     */
    private $nostoScopeHelper;

    /**
     * @var bool
     */
    private $isInteractive = true;

    /**
     * @var WriterInterface
     */
    private $config;

    /**
     * NostoAccountRemoveCommand constructor.
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoScopeHelper $nostoScopeHelper
     * @param WriterInterface $appConfig
     */
    public function __construct(
        NostoAccountHelper $nostoAccountHelper,
        NostoScopeHelper $nostoScopeHelper,
        WriterInterface $appConfig
    )
    {
        $this->nostoAccountHelper = $nostoAccountHelper;
        $this->nostoScopeHelper  = $nostoScopeHelper;
        $this->config = $appConfig;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('nosto:account:remove')
            ->setDescription('Remove Nosto Account Via CLI')
            ->addOption(
                self::NOSTO_ACCOUNT_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Nosto Account ID to be removed (Should exist already)'
            )->addOption(
                self::SCOPE_CODE,
                null,
                InputOption::VALUE_REQUIRED,
                'Store view code'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->isInteractive = !$input->getOption('no-interaction');
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getOption(self::NOSTO_ACCOUNT_ID) ?:
            $io->ask('Enter Nosto Account Id: ');
        $scopeCode = $input->getOption(self::SCOPE_CODE) ?:
            $io->ask('Enter Store Scope Code');

        if ($this->removeNostoAccount($io, $accountId, $scopeCode)) {
            $io->success('Nosto account removed successfully');
        } else {
            $io->error('Could not complete operation');
        }
    }

    /**
     * Removes the local installation for the specific store
     *
     * @param SymfonyStyle $io
     * @param $accountId
     * @param $scopeCode
     * @return bool
     */
    private function removeNostoAccount(SymfonyStyle $io, $accountId, $scopeCode)
    {
        $store = $this->getStoreByCode($scopeCode);
        if(!$store){
            $io->error('Store not found. Check your input.');
            return false;
        }
        $storeAccountId = $store->getConfig(NostoAccountHelper::XML_PATH_ACCOUNT);
        $account = $this->nostoAccountHelper->findAccount($store);
        if ($account && $storeAccountId === $accountId) {
            // If the script is non-interactive, do not ask for confirmation
            $confirmOverride = $this->isInteractive ?
                $confirmOverride = $io->confirm(
                    'Local Nosto account found for this store view. Remove account?',
                    false
                ):
                true;
            if ($confirmOverride) {
                return $this->deleteAccount($store);
            }
        } else {
            $io->error('Account not found or not connected with specific store. Check your input.');
            return false;
        }
    }

    /**
     * @param $scopeCode the storeview code
     * @return \Magento\Store\Model\Store
     */
    private function getStoreByCode($scopeCode)
    {
        $stores = $this->nostoScopeHelper->getStores();
        foreach ($stores as $store) {
            if ($store->getCode() === $scopeCode) {
                return $store;
            }
        }
    }

    /**
     * @param Store $store
     * @return bool
     */
    private function deleteAccount(Store $store) {
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

}