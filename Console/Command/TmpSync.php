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

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Nosto\Tagging\Model\Product\Service as NostoProductService;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TmpSync extends Command
{
    private $logger;
    private $state;
    private $nostoProductService;
    private $nostoProductRepository;

    public static $productUpdateInterval = 1120;

    /**
     * Constructor to instantiating the reindex command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     *
     * @param State $state
     * @param LoggerInterface $logger
     * @param NostoProductService\Proxy $nostoProductService
     * @param NostoProductRepository\Proxy $nostoProductRepository
     */
    public function __construct(
        State $state,
        LoggerInterface $logger,
        NostoProductService\Proxy $nostoProductService,
        NostoProductRepository\Proxy $nostoProductRepository
    ) {
        parent::__construct();

        $this->state = $state;
        $this->logger = $logger;
        $this->nostoProductService = $nostoProductService;
        $this->nostoProductRepository = $nostoProductRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('nosto:sync:tmp');
        $this->setDescription('Syncs the product catalog with Nosto');
    }

    /**
     * Main CLI method that loops through each of the store views and then fetches products in the
     * specified batch size. This continues until there are no more products returned by the
     * product collection factory.
     *
     * @param InputInterface $input the command line input interface for reading arguments
     * @param OutputInterface $output the command line output interface for logging
     * @return int|null|void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->getAreaCode();
        } catch (LocalizedException $e) {
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        }
        $intervalSpec = sprintf('PT%dM', self::$productUpdateInterval);
        $interval = new \DateInterval($intervalSpec);
        $products = $this->nostoProductRepository->getUpdatedWithinInterval($interval)->getItems();
        $output->writeln(
            sprintf(
                'Updating %d products to Nosto using interval %s',
                count($products),
                $intervalSpec
            )
        );
        try {
            $this->nostoProductService->update($products);
        } catch (\Exception $e) {
            // ToDo - add logging
            $this->logger->error($e->getMessage());
            die('FAIL');
        }
    }
}
