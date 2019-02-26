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

namespace Nosto\Tagging\Util;

class Memory
{
    const MB_DIVIDER = 1048576;

    /**
     * Returns the runtime memory limit
     *
     * @return string
     */
    public static function getTotalMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    /**
     * Returns the runtime memory consumption for the whole PHP
     *
     * @param bool $mb (if true the memory consumption is returned in megabytes)
     * @return string
     */
    public static function getRealConsumption($mb = true)
    {
        $mem = memory_get_usage(true);
        if ($mb === true) {
            $mem = round($mem/self::MB_DIVIDER, 2);
        }

        return $mem;
    }

    /**
     * Returns the runtime memory consumption for the current PHP script
     *
     * @param bool $mb (if true the memory consumption is returned in megabytes)
     * @return string
     */
    public static function getConsumption($mb = true)
    {
        $mem = memory_get_usage(false);
        if ($mb === true) {
            $mem = round($mem/self::MB_DIVIDER, 2);
        }

        return $mem;
    }

    /**
     * @return float The percentage of used memory by the script
     */
    public static function getPercentageUsedMem()
    {
        $memLimit = self::getTotalMemoryLimit();
        // ini_get returns a string as it is defined in the php.ini file
        // It is possible to use M or G to define the amount of memory
        if (strpos($memLimit, 'G')) {
            $memLimit = (int)$memLimit * 1024; // Cast to remove 'G' and 'GB'
        } else { // Else we assume it is in Megabytes
            $memLimit = (int)$memLimit;
        }
        return (float)(self::getConsumption() / $memLimit) * 100;
    }
}
