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

namespace Nosto\Tagging\Util;

use Nosto\NostoException;

class Benchmark
{
    /** @var array */
    private array $times = [];
    /** @var array */
    private array $ticks = [];
    /** @var array */
    private array $checkpoints = [];
    /** @var array */
    private array $checkpointTimes = [];
    /** @var Benchmark|null */
    private static ?Benchmark $instance = null;

    /**
     * Prevent instance
     */
    private function __construct()
    {
        // Private
    }

    /**
     * Returns singleton instance
     *
     * @return Benchmark
     */
    public static function getInstance(): Benchmark
    {
        if (self::$instance === null) {
            self::$instance = new Benchmark();
        }
        return self::$instance;
    }

    /**
     * Start instrumentation
     *
     * @param string $name
     * @param int $checkpoint
     */
    public function startInstrumentation(string $name, int $checkpoint)
    {
        $this->resetTimer($name);
        $this->checkpoints[$name] = $checkpoint;
        $this->checkpointTimes[$name] = [];
        $this->ticks[$name] = 0;
    }

    /**
     * Reset timer
     *
     * @param $name
     * @return void
     */
    public function resetTimer($name)
    {
        $this->times[$name] = microtime(true);
    }

    /**
     * Stop instrumentation
     *
     * @param string $name
     */
    public function stopInstrumentation(string $name)
    {
        $this->checkpointTimes[$name][] = $this->getElapsed($name);
    }

    /**
     * Return elapsed time for timer
     *
     * @param string $name
     * @return float
     */
    public function getElapsed(string $name)
    {
        if (empty($this->times[$name])) {
            return 0;
        }
        return microtime(true) - $this->times[$name];
    }

    /**
     * Calculates one call for given name. When checkpoint is reached the elapsed time
     * is stored into the checkpoints array. Returns elapsed time when checkpoint is reached, otherwise
     * null.
     *
     * @param string $name
     * @return float|null
     */
    public function tick(string $name)
    {
        ++$this->ticks[$name];
        if ($this->ticks[$name] % $this->checkpoints[$name] === 0) {
            $elapsed = $this->getElapsed($name);
            $this->checkpointTimes[$name][] = $elapsed;
            $this->resetTimer($name);
            return $elapsed;
        }

        return null;
    }

    /**
     * Returns recorded times in for a specific measurement name
     *
     * @param string $name
     * @return array
     * @throws NostoException
     */
    public function getCheckpointTimes(string $name): array
    {
        if (!isset($this->checkpointTimes[$name])) {
            throw new NostoException(sprintf('No breakpoints found for %s', $name));
        }
        return $this->checkpointTimes[$name];
    }

    /**
     * Returns the amount of ticks for given name
     *
     * @param string $name
     * @return int
     * @throws NostoException
     */
    public function getTickCount(string $name): int
    {
        if (!isset($this->ticks[$name])) {
            throw new NostoException(sprintf('No ticks defined for %s', $name));
        }
        return $this->ticks[$name];
    }

    /**
     * Returns the avg time for each tick
     *
     * @param string $name
     * @return float
     * @throws NostoException
     */
    public function getAvgTickTime(string $name): float
    {
        if (!isset($this->checkpointTimes[$name])) {
            throw new NostoException(sprintf('No breakpoints found for %s', $name));
        }
        $ticks = $this->getTickCount($name) > 0 ? $this->getTickCount($name) : 1;
        return round($this->getTotalTime($name) / $ticks, 6);
    }

    /**
     * Returns the total recorded time for given name
     *
     * @param string $name
     * @return float
     * @throws NostoException
     */
    public function getTotalTime(string $name): float
    {
        if (!isset($this->checkpointTimes[$name])) {
            throw new NostoException(sprintf('No breakpoints found for %s', $name));
        }
        return round(array_sum($this->checkpointTimes[$name]), 4);
    }
}
