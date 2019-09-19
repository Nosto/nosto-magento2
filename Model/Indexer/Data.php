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

namespace Nosto\Tagging\Model\Indexer;

use Exception;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Nosto\NostoException;
use Nosto\Tagging\Model\Indexer\Data\ModeSwitcher;
use Nosto\Tagging\Model\Indexer\Data\DimensionModeConfiguration;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Exception\MemoryOutOfBoundsException;
use Magento\Store\Model\StoreDimensionProvider;
use Magento\Indexer\Model\ProcessManager;
use Nosto\Tagging\Util\Benchmark;

/**
 * An indexer for Nosto product sync
 */
class Data implements IndexerActionInterface, MviewActionInterface, DimensionalIndexerInterface
{
    const INDEXER_ID = 'nosto_index_product_data_sync';

    /** @var NostoIndexService */
    private $nostoServiceIndex;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var DimensionProviderInterface; */
    private $dimensionProvider;

    /** @var ModeSwitcher */
    private $modeSwitcher;

    /** @var NostoLogger */
    private $nostoLogger;

    /** @var ProcessManager */
    private $processManager;

    /**
     * Data constructor.
     * @param NostoIndexService $nostoServiceIndex
     * @param NostoHelperAccount $nostoHelperAccount
     * @param StoreDimensionProvider $storeDimensionProvider
     * @param NostoLogger $nostoLogger
     */
    public function __construct(
        NostoIndexService $nostoServiceIndex,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        DimensionProviderInterface $dimensionProvider,
        ModeSwitcher $modeSwitcher,
        NostoLogger $nostoLogger,
        ProcessManager $processManager = null
    ) {
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->dimensionProvider = $dimensionProvider;
        $this->modeSwitcher = $modeSwitcher;
        $this->nostoLogger = $nostoLogger;
        $this->processManager = $processManager ?: \Magento\Framework\App\ObjectManager::getInstance()->get(
            ProcessManager::class
        );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeFull()
    {
        $dimensionProvider = $this->getDimensionsProvider();
        if ($dimensionProvider === null) {
            $this->executeInSequence();
        } else {
            $this->executeInParallel($dimensionProvider);
        }
    }

    private function executeInSequence(array $ids = [])
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            try {
                $this->nostoServiceIndex->indexProducts($store, $ids);
                // Catch only MemoryOutOfBoundsException as this is the most expected ones
                // And the ones we are interested of
            } catch (MemoryOutOfBoundsException $e) {
                $this->nostoLogger->error($e->getMessage());
            }
        }
    }

    private function executeInParallel(DimensionProviderInterface $dimensionProvider)
    {
        $userFunctions = [];
        foreach ($dimensionProvider->getIterator() as $dimension) {
            $userFunctions[] = function () use ($dimension) {
                $this->executeByDimensions($dimension);
            };
        }
        $this->processManager->execute($userFunctions);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function execute($ids)
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($storesWithNosto as $store) {
            try {
                $this->nostoServiceIndex->indexProducts($store, $ids);
                // Catch only MemoryOutOfBoundsException as this is the most expected ones
                // And the ones we are interested of
            } catch (MemoryOutOfBoundsException $e) {
                $this->nostoLogger->error($e->getMessage());
            }
        }
    }

    public function executeByDimensions(array $dimensions, \Traversable $entityIds = null)
    {
        if (count($dimensions) > 1 || !isset($dimensions[StoreDimensionProvider::DIMENSION_NAME])) {
            throw new \InvalidArgumentException('Indexer "' . self::INDEXER_ID . '" support only Store dimension');
        }

        $storeId = $dimensions[StoreDimensionProvider::DIMENSION_NAME]->getValue();
        $store = $this->nostoHelperScope->getStore($storeId);
        $benchmarkName = sprintf('STORE-DIMENSION-%s', $store->getCode());
        Benchmark::getInstance()->startInstrumentation($benchmarkName, 0);
        $this->nostoLogger->info('[START] NOSTO-DIMENSION store:'. $store->getName());
        try {
            $this->nostoServiceIndex->indexProducts($store);
            // Catch only MemoryOutOfBoundsException as this is the most expected ones
            // And the ones we are interested of
        } catch (MemoryOutOfBoundsException $e) {
            $this->nostoLogger->error($e->getMessage());
        } catch (NostoException $e) {
            $this->nostoLogger->error($e->getMessage());
        }

        Benchmark::getInstance()->stopInstrumentation($benchmarkName);
        $duration = Benchmark::getInstance()->getElapsed($benchmarkName);
        $this->nostoLogger->info('[END] NOSTO-DIMENSION store:'. $store->getName() . '('.round($duration,2).' secs)');
    }

    /**
     * @return DimensionProviderInterface|null
     */
    public function getDimensionsProvider()
    {
        $mode = $this->modeSwitcher->getMode();
        if ($mode === DimensionModeConfiguration::DIMENSION_NONE) {
            return null;
        } elseif ($mode === DimensionModeConfiguration::DIMENSION_STORE) {
            return $this->dimensionProvider;
        }
        return null;
    }
}
