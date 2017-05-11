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

use Exception;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Object\Signup\Account;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Product\Collection as NostoProductCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reindex extends Command
{
    private $productCollectionFactory;
    private $productVisibility;
    private $storeManager;
    private $nostoHelperAccount;
    private $nostoProductCollection;
    private $moduleManager;
    private $logger;
    private $state;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param State $state
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductVisibility $productVisibility
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     * @param NostoHelperAccount\Proxy $nostoHelperAccount
     * @param NostoProductCollection\Proxy $nostoProductCollection
     */
    public function __construct(
        State $state,
        ProductCollectionFactory $productCollectionFactory,
        ProductVisibility $productVisibility,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        NostoHelperAccount\Proxy $nostoHelperAccount,
        NostoProductCollection\Proxy $nostoProductCollection
    ) {
        $state->setAreaCode(Area::AREA_FRONTEND);
        parent::__construct();

        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->storeManager = $storeManager;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoProductCollection = $nostoProductCollection;
        $this->moduleManager = $moduleManager;
        $this->logger = $logger;
        $this->state = $state;

        HttpRequest::$responseTimeout = 60;
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('nosto:reindex');
        $this->setDescription('Syncs the product catalog with Nosto');
        $this->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Products per batch', 20);
    }

    /**
     * Main CLI method that loops through each of the store views and then fetches products in the
     * specified batch size. This continues until there are no more products returned by the
     * product collection factory.
     *
     * @param InputInterface $input the command line input interface for reading arguments
     * @param OutputInterface $output the command line output interface for logging
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            $limit = (int)$input->getOption('batch');

            foreach ($this->storeManager->getStores() as $store) {
                /** @var Account $account */
                $account = $this->nostoHelperAccount->findAccount($store);
                if ($account === null) {
                    continue;
                }

                try {
                    $count = 0;
                    while (true) {
                        $output->writeln("Fetching $limit products from offset $count");
                        $this->logger->info("Fetching $limit products from offset $count");

                        $products = $this->nostoProductCollection->buildMany($store, $limit,
                            $count);
                        if (empty($products) || !$products || count($products) === 0) {
                            break;
                        }

                        $op = new UpsertProduct($account);
                        foreach ($products as $product) {
                            $op->addProduct($product);
                        }

                        $op->upsert();
                        $count = $count + $limit;
                    }
                } catch (Exception $e) {
                    $output->writeln("An error occurred");
                    $output->writeln($e->getMessage());
                    $this->logger->error($e->__toString());
                }
            }
        }
    }
}