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

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Nosto\Tagging\Cron\ProductDataCron;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class NostoRebuildInvalidProductData
 * This command is mainly for debugging purposes - the cron job should do the trick
 * @package Nosto\Tagging\Console\Command
 */
class NostoRebuildInvalidProductData extends Command
{
    /**
     * @var ProductDataCron
     */
    private $productDataCron;

    /** @var State */
    private $appState;

    /**
     * NostoConfigConnectCommand constructor.
     * @param ProductDataCron $productDataCron
     * @param State $appState
     */
    public function __construct(
        ProductDataCron $productDataCron,
        State $appState
    ) {
        $this->productDataCron = $productDataCron;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('nosto:data:rebuild-invalid-products')
            ->setDescription(
                'Rebuilds the Nosto product data for "dirty" products - '
                . 'this command is meant mainly for debugging purposes'
            );
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $this->handleArea();
            $this->productDataCron->execute();
            $io->success('Nosto product data rebuild manually');
        } catch (\Exception $e) {
            $io->error(sprintf('An error occurred - message was %s', $e->getMessage()));
            return;
        }
    }

    /**
     * @throws LocalizedException
     */
    private function handleArea()
    {
        try {
            $originalArea = $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            $originalArea = null;
        }
        if ($originalArea === null) {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        }
    }
}
