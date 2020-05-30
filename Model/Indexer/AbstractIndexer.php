<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Indexer;

use ArrayIterator;
use Exception;
use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\Dimension;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Indexer\Model\ProcessManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\AbstractDimensionModeConfiguration as DimensionModeConfiguration;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Nosto\Tagging\Util\Benchmark;
use Symfony\Component\Console\Input\InputInterface;
use Traversable;
use UnexpectedValueException;

/**
 * Class AbstractIndexer
 */
abstract class AbstractIndexer implements DimensionalIndexerInterface, IndexerActionInterface, MviewActionInterface
{
    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoLogger */
    public $nostoLogger;

    /** @var ProcessManager */
    private $processManager;

    /** @var DimensionProviderInterface */
    private $dimensionProvider;

    /** @var Emulation */
    private $storeEmulator;

    /** @var InputInterface */
    private $input;

    /** @var IndexerStatusServiceInterface */
    private $indexerStatusService;

    /**
     * AbstractIndexer constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $nostoLogger
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulator
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     * @param ProcessManager|null $processManager
     */
    public function __construct(
        NostoHelperScope $nostoHelperScope,
        NostoLogger $nostoLogger,
        StoreDimensionProvider $dimensionProvider,
        Emulation $storeEmulator,
        InputInterface $input,
        IndexerStatusServiceInterface $indexerStatusService,
        ProcessManager $processManager = null
    ) {
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoLogger = $nostoLogger;
        $this->dimensionProvider = $dimensionProvider;
        $this->processManager = $processManager;
        $this->input = $input;
        $this->storeEmulator = $storeEmulator;
        $this->indexerStatusService = $indexerStatusService;
    }

    /**
     * Get ModeSwitcher class to later get the indexer mode
     *
     * @return ModeSwitcherInterface
     */
    abstract public function getModeSwitcher(): ModeSwitcherInterface;

    /**
     * Implement logic of single store indexing
     *
     * @param Store $store
     * @param array $ids
     * @throws Exception
     */
    abstract public function doIndex(Store $store, array $ids = []);

    /**
     * @return string
     */
    abstract public function getIndexerId(): string;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeFull()
    {
        if ($this->allowFullExecution() === true) {
            $indexerId = $this->getIndexerId();
            $message = sprintf(
                'Begin a full reindex for indexer "%s"',
                $indexerId
            );
            $this->nostoLogger->info($message);
            $this->doWork();
            $this->nostoLogger->info('Finished full reindex');
        }
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
        $indexerId = $this->getIndexerId();
        $message = sprintf(
            'Begin a row reindex for indexer "%s" for "%s"',
            $indexerId,
            $id
        );
        $this->nostoLogger->info($message);
        $this->execute([$id]);
        $this->nostoLogger->info('Finished row reindex');
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function execute($ids)
    {
        $indexerId = $this->getIndexerId();
        $idCount = count($ids);
        $totalEntries = $this->getTotalCLRows();
        $message = sprintf(
            'Begin a partial reindex for indexer "%s" for "%d ids. ' .
            'Total number of entries in CL table: "%s"',
            $indexerId,
            $idCount,
            $totalEntries
        );
        $this->nostoLogger->info($message);
        $this->doWork($ids);
        $this->nostoLogger->info('Finished partial reindex');
    }

	/**
	 * @param array $ids
	 * @throws Exception
	 * @suppress PhanTypeMismatchArgument
	 */
    public function doWork(array $ids = [])
    {
        $userFunctions = [];
        $mode = $this->getModeSwitcher()->getMode();
        $this->nostoLogger->info(sprintf('Indexing by mode "%s"', $mode));
        switch ($mode) {
            case DimensionModeConfiguration::DIMENSION_NONE:
                /** @var Dimension[] $dimension */
                foreach ($this->dimensionProvider->getIterator() as $dimension) {
                    if (is_array($dimension)) {
                        (function () use ($dimension, $ids) {
                            $this->executeByDimensions($dimension, new ArrayIterator($ids));
                        })();
                    }
                }
                break;
            case DimensionModeConfiguration::DIMENSION_STORE:
                /** @var Dimension[] $dimension  */
                foreach ($this->dimensionProvider->getIterator() as $dimension) {
                    /** @suppress PhanTypeMismatchArgument */
                    $userFunctions[] = function () use ($dimension, $ids) {
                        $this->executeByDimensions($dimension, new ArrayIterator($ids));
                    };
                }
                /** @var Traversable $userFunctions  */
                $this->getProcessManager()->execute($userFunctions);
                break;
            default:
                throw new UnexpectedValueException('Undefined dimension mode.');
        }
        $this->clearProcessedChangelog();
    }

    /**
     * In case processManager has value null, pass a new instance of ProcessManager
     * This operation is not done in the constructor in order for the constr to have only
     * value assignments
     *
     * @return ProcessManager
     */
    private function getProcessManager()
    {
        if ($this->processManager ===  null) {
            $this->processManager = ObjectManager::getInstance()->get(
                ProcessManager::class
            );
        }
        return $this->processManager;
    }

    /**
     * @param Dimension[] $dimensions
     * @param Traversable|null $entityIds
     */
    public function executeByDimensions(array $dimensions, Traversable $entityIds = null)
    {
        if (count($dimensions) > 1 || !isset($dimensions[StoreDimensionProvider::DIMENSION_NAME])) {
            throw new InvalidArgumentException('Indexer "' . $this->getIndexerId() . '" support only Store dimension');
        }

        $storeId = $dimensions[StoreDimensionProvider::DIMENSION_NAME]->getValue();
        $store = $this->nostoHelperScope->getStore($storeId);
        $benchmarkName = sprintf('STORE-DIMENSION-%s', $store->getCode());
        Benchmark::getInstance()->startInstrumentation($benchmarkName, 0);
        $this->nostoLogger->info(sprintf('[START] Processing dimension: "%s"', $store->getCode()));
        $ids = [];
        if ($entityIds !== null) {
            $ids = iterator_to_array($entityIds);
        }
        $this->storeEmulator->startEnvironmentEmulation((int)$storeId);
        try {
            $this->doIndex($store, $ids);
        } catch (Exception $e) {
            $this->nostoLogger->error($e->getMessage());
        } finally {
            $this->storeEmulator->stopEnvironmentEmulation();
        }

        Benchmark::getInstance()->stopInstrumentation($benchmarkName);
        $duration = Benchmark::getInstance()->getElapsed($benchmarkName);
        $this->nostoLogger->info(
            sprintf(
                '[END] Finished processing dimension: "%s", (%f)',
                $store->getCode(),
                round($duration, 2)
            )
        );
    }

    /**
     * @return bool
     */
    public function allowFullExecution(): bool
    {
        return IndexerUtil::isCalledFromSetupUpgrade($this->input) === false;
    }

	/**
	 * Clears the CL tables
	 * @throws Exception
	 */
    private function clearProcessedChangelog()
    {
        $benchmarkName = sprintf('CHANGELOG-CLEANUP-%s', $this->getIndexerId());
        Benchmark::getInstance()->startInstrumentation($benchmarkName, 0);
        $this->nostoLogger->debug(
            sprintf(
                'Cleaning up the CL tables for indexer %s',
                $this->getIndexerId()
            )
        );
        $this->indexerStatusService->clearProcessedChangelog($this->getIndexerId());
        Benchmark::getInstance()->stopInstrumentation($benchmarkName);
        $duration = round(Benchmark::getInstance()->getElapsed($benchmarkName), 4);
        $this->nostoLogger->debug(
            sprintf(
                'Cleanup took %f secs. Rows left in changelog table %d. Cleanup up until version #%d',
                $duration,
                $this->getTotalCLRows(),
                $this->indexerStatusService->getCurrentWatermark($this->getIndexerId())
            )
        );
    }

    /**
     * @return int
     * @throws Exception
     */
    private function getTotalCLRows()
    {
        return $this->indexerStatusService->getTotalChangelogCount($this->getIndexerId());
    }
}
