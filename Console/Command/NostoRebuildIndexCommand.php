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

namespace Nosto\Tagging\Console\Command;

use Exception;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Service\Index as NostoServiceIndex;
use Nosto\Tagging\Model\Indexer\Invalidate as InvalidateIndexer;

class NostoRebuildIndexCommand extends Command
{
    const SCOPE_CODE = 'scope-code';

    /** @var bool */
    private $isInteractive = true;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoServiceIndex */
    private $nostoServiceIndex;

    /** @var InvalidateIndexer */
    private $invalidateIndexer;

    /** @var SymfonyStyle*/
    private $io;

    /**
     * NostoRebuildIndexCommand constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoServiceIndex $nostoServiceIndex
     * @param InvalidateIndexer $invalidateIndexer
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoServiceIndex $nostoServiceIndex,
        InvalidateIndexer $invalidateIndexer
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->invalidateIndexer = $invalidateIndexer;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('nosto:index:rebuild')
            ->setDescription('Rebuild Nosto Product Index Via CLI')
            ->addOption(
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->isInteractive = !$input->getOption('no-interaction');
        $this->io = new SymfonyStyle($input, $output);
        $scopeCode = $input->getOption(self::SCOPE_CODE) ?:
            $this->io->ask(
                'Enter store scope code or press enter to rebuilt index in all stores'
            );
        $success = true;
        if (empty($scopeCode)) {
            $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
            foreach ($storesWithNosto as $store) {
                $this->io->block(sprintf('Rebuilding index for store %s', $store->getCode()));
                $success = $this->rebuildIndex($store);
            }
        } else {
            $store = $this->nostoHelperScope->getStoreByCode($scopeCode);
            if ($this->nostoHelperAccount->nostoInstalledAndEnabled($store)) {
                $success = $this->rebuildIndex($store);
            } else {
                $this->io->error('Store given is not connected to Nosto');
                $success = false;
            }
        }

        if ($success) {
            $this->io->success('Nosto product index was successfully rebuilt');
        } else {
            $this->io->error('Could not complete operation');
        }
    }

    /**
     * Gets collection of all products for given store,
     * triggers index rebuild and return bool
     *
     * @param Store $store
     * @return bool
     */
    private function rebuildIndex(Store $store)
    {
        try {
            $indexCollection = $this->invalidateIndexer->getCollection($store);
            $this->nostoServiceIndex->handleProductChange($indexCollection, $store);
        } catch (Exception $e) {
            $this->io->error($e->getMessage());
            return false;
        }
        return true;
    }
}
