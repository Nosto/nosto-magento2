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

namespace Nosto\Tagging\Model\Service;

use Exception;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Util\Benchmark;
use Nosto\Util\Memory as NostoMemUtil;

abstract class AbstractService
{
    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /** @var NostoLogger */
    private $nostoLogger;

    /**
     * AbstractService constructor.
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoLogger $nostoLogger
     */
    public function __construct(
        NostoDataHelper $nostoDataHelper,
        NostoLogger $nostoLogger
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoLogger = $nostoLogger;
    }

    /**
     * Throws new memory out of bounds exception if the memory
     * consumption is higher than configured amount
     *
     * @param string $serviceName
     * @throws MemoryOutOfBoundsException
     */
    public function checkMemoryConsumption($serviceName)
    {
        $maxMemPercentage = $this->nostoDataHelper->getIndexerMemory();
        if (NostoMemUtil::getPercentageUsedMem() >= $maxMemPercentage) {
            throw new MemoryOutOfBoundsException(
                sprintf(
                    'Memory Out Of Bounds Error: Memory used by %s is over %d%% allowed',
                    $serviceName,
                    $maxMemPercentage
                )
            );
        }
    }

    /**
     * @param string $name
     * @param int $breakpoint
     */
    public function startBenchmark(string $name, int $breakpoint)
    {
        Benchmark::getInstance()->startInstrumentation($name, $breakpoint);
    }

    /**
     * Records calls this function and writes log if breakpoint is reached
     *
     * @param string $name
     * @param bool $writeLog if set to true debug log will be written
     * @return float|null
     * @throws Exception
     */
    public function tickBenchmark(string $name, $writeLog = false)
    {
        $elapsed = Benchmark::getInstance()->tick($name);
        if ($elapsed !== null) {
            if ($writeLog === true) {
                $reachedBreakpoints = count(Benchmark::getInstance()->getCheckpointTimes($name));
                $this->nostoLogger->logWithMemoryConsumption(
                    sprintf(
                        'Execution for %s took %f seconds - checkpoints reached %d',
                        $name,
                        $elapsed,
                        $reachedBreakpoints
                    )
                );
            }
            return $elapsed;
        }
        return null;
    }

    /**
     * Logs the recorded benchmark summary
     *
     * @param string $name
     * @param Store $store
     */
    public function logBenchmarkSummary(string $name, Store $store)
    {
        try {
            Benchmark::getInstance()->stopInstrumentation($name);
            $this->nostoLogger->logWithMemoryConsumption(sprintf(
                'Summary of processing %s for store %s. Total amount of iterations %d'
                . ', single iteration took on avg %f sec, total time was %f sec',
                $name,
                $store->getName(),
                Benchmark::getInstance()->getTickCount($name),
                Benchmark::getInstance()->getAvgTickTime($name),
                Benchmark::getInstance()->getTotalTime($name)
            ));
        } catch (NostoException $e) {
            $this->nostoLogger->exception($e);
        }
    }

    /**
     * @return NostoLogger
     */
    public function getLogger()
    {
        return $this->nostoLogger;
    }
}
