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

namespace Nosto\Tagging\Util;

class Benchmark
{
    private $times = [];
    private $ticks = [];
    private $breakpoints = [];
    private $breakpointTimes = [];
    private static $instance;

    /**
     * Prevent instance
     */
    private function __construct()
    {
        // Private
    }

    /**
     * Returns singleton instance
     * @return Benchmark
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Benchmark();
        }
        return self::$instance;
    }

    /**
     * @param string $name
     * @param int $breakpoint
     */
    public function startInstrumentation(string $name, int $breakpoint)
    {
        $this->resetTimer($name);
        $this->breakpoints[$name] = $breakpoint;
        $this->breakpointTimes[$name] = [];
        $this->ticks[$name] = 0;
    }

    public function resetTimer($name)
    {
        $this->times[$name] = microtime(true);
    }

    /**
     * @param string $name
     * @throws \Exception
     */
    public function stopInstrumentation(string $name)
    {
        $this->breakpointTimes[$name][] = $this->getElapsed($name);
    }

    /**
     * @param $name
     * @return float
     * @throws \Exception
     */
    public function getElapsed($name)
    {
        if (empty($this->times[$name])) {
            throw new \Exception(sprintf('No such instrumentation: %s', $name));
        }
        return microtime(true) - $this->times[$name];
    }

    /**
     * Calculates one call for given name. When breakpoint is reached the elapsed time
     * is stored into the breakpoints array. Returns elapsed time when breakpoint is reached, otherwise
     * null.
     *
     * @param string $name
     * @param int $breakpoint
     * @return float|null
     * @throws \Exception
     */
    public function tick($name)
    {
        ++$this->ticks[$name];
        if ($this->ticks[$name] % $this->breakpoints[$name] === 0) {
            $elapsed = $this->getElapsed($name);
            $this->breakpointTimes[$name][] = $elapsed;
            $this->resetTimer($name);
            return $elapsed;
        }

        return null;
    }

    /**
     * Returns recorder times in a specific breakpoint
     *
     * @param $name
     * @return array
     * @throws \Exception
     */
    public function getBreakpointTimes($name)
    {
        if (!isset($this->breakpointTimes[$name])) {
            throw new \Exception(sprintf('No breakpoints found for %s', $name));
        }
        return $this->breakpointTimes[$name];
    }

    /**
     * Returns the amount of ticks for given name
     *
     * @param $name
     * @return int
     * @throws \Exception
     */
    public function getTickCount($name)
    {
        if (!isset($this->ticks[$name])) {
            throw new \Exception(sprintf('No ticks defined for %s', $name));
        }
        return $this->ticks[$name];
    }

    /**
     * Returns the avg time for each tick
     *
     * @param $name
     * @return int
     * @throws \Exception
     */
    public function getAvgTickTime($name)
    {
        if (!isset($this->breakpointTimes[$name])) {
            throw new \Exception(sprintf('No breakpoints found for %s', $name));
        }
        // ToDo - check if there not enough ticks for the first break point
        return round($this->getTotalTime($name)/$this->getTickCount($name), 6);
    }

    /**
     * Returns the total recorded time for given name
     *
     * @param $name
     * @return int
     * @throws \Exception
     */
    public function getTotalTime($name)
    {
        if (!isset($this->breakpointTimes[$name])) {
            throw new \Exception(sprintf('No breakpoints found for %s', $name));
        }
        return round(array_sum($this->breakpointTimes[$name]), 4);
    }
}
