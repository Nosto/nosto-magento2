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

use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Framework\Indexer\Dimension;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Nosto\NostoException;
use Nosto\Tagging\Model\Indexer\Dimensions\AbstractDimensionModeConfiguration as DimensionModeConfiguration;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Store\Model\StoreDimensionProvider;
use Magento\Indexer\Model\ProcessManager;
use Nosto\Tagging\Util\Benchmark;
use Magento\Framework\App\ObjectManager;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Magento\Store\Model\Store;
use Traversable;
use ArrayIterator;
use InvalidArgumentException;
use UnexpectedValueException;

abstract class AbstractIndexer implements DimensionalIndexerInterface, IndexerActionInterface, MviewActionInterface
{
    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoLogger */
    public $nostoLogger;

    /** @var ProcessManager */
    private $processManager;

    /** @var DimensionProviderInterface */
    private $dimensionProvider;

    /**
     * AbstractIndexer constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $nostoLogger
     * @param StoreDimensionProvider $dimensionProvider
     * @param ProcessManager|null $processManager
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $nostoLogger,
        StoreDimensionProvider $dimensionProvider,
        ProcessManager $processManager = null
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoLogger = $nostoLogger;
        $this->dimensionProvider = $dimensionProvider;
        $this->processManager = $processManager;
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
     */
    abstract public function doIndex(Store $store, array $ids = []);

    /**
     * @return string
     */
    abstract public function getIndexerId(): string ;

    /**
     * @param array $ids
     * @suppress PhanTypeMismatchArgument
     */
    public function doWork(array $ids = [])
    {
        $userFunctions = [];

        switch ($this->getModeSwitcher()->getMode()) {
            case DimensionModeConfiguration::DIMENSION_NONE:
                foreach ($this->dimensionProvider->getIterator() as $dimension) {
                    /** @suppress PhanTypeMismatchArgument */
                    $this->executeByDimensions($dimension, new ArrayIterator($ids));
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
                throw new UnexpectedValueException("Undefined dimension mode.");
        }
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
     * @throws NostoException
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
        $this->nostoLogger->info('[START] NOSTO-DIMENSION store:'. $store->getName());
        $ids = [];
        if ($entityIds !== null) {
            $ids = iterator_to_array($entityIds);
        }

        $this->doIndex($store, $ids);

        Benchmark::getInstance()->stopInstrumentation($benchmarkName);
        $duration = Benchmark::getInstance()->getElapsed($benchmarkName);
        $this->nostoLogger->info(
            '[END] NOSTO-DIMENSION store:' . $store->getName() . '(' . round($duration, 2) . ' secs)'
        );
    }
}
