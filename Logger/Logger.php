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

namespace Nosto\Tagging\Logger;

use Magento\Store\Model\Store;
use Monolog\Logger as MonologLogger;
use Nosto\Tagging\Helper\NewRelic;
use Nosto\Util\Memory;
use Throwable;

class Logger extends MonologLogger
{
    /**
     * Logs an exception and sends it to New relic if available
     * @param Throwable $exception
     * @return bool
     */
    public function exception(Throwable $exception)
    {
        NewRelic::reportException($exception);
        return $this->error($exception->__toString());
    }

    /**
     * Logs a message along with the memory consumption
     *
     * @param $message
     * @param Store|null $store
     * @param null $sourceClass
     * @return bool
     */
    public function logWithMemoryConsumption($message, Store $store = null, $sourceClass = null)
    {
        $msg = sprintf(
            '%s [mem usage: %sM / %s] [realmem: %sM]',
            $message,
            Memory::getConsumption(),
            Memory::getTotalMemoryLimit(),
            Memory::getRealConsumption()
        );
        $context = [];
        if ($store) {
            $context['storeId'] = $store->getId();
        }
        if (is_object($sourceClass)) {
            return $this->debugWithSource($message, $context, $sourceClass);
        }
        return $this->debug($msg, $context);
    }

    /**
     * Logs a debug level message with given source class info
     *
     * @param $message
     * @param array $context
     * @param object $sourceClass
     * @return bool
     */
    public function debugWithSource($message, array $context, $sourceClass)
    {
        return $this->logWithSource($message, $context, $sourceClass, 'debug');
    }

    /**
     * Logs an info level message with given source class info
     *
     * @param $message
     * @param array $context
     * @param object $sourceClass
     * @return bool
     */
    public function infoWithSource($message, array $context, $sourceClass)
    {
        return $this->logWithSource($message, $context, $sourceClass, 'info');
    }

    /**
     * @param $message
     * @param $context
     * @param $sourceClass
     * @param $level
     * @return bool
     */
    private function logWithSource($message, $context, $sourceClass, $level)
    {
        $context['sourceClass'] = get_class($sourceClass);
        if ($level === 'info') {
            return $this->info($message, $context);
        }
        return $this->debug($message, $context);
    }
}
