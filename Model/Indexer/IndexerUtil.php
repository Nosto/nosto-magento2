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

use Exception;
use Symfony\Component\Console\Input\InputInterface;

class IndexerUtil
{
    /** Non-ambiguous scope for settings commands */
    const SETUP_UPGRADE_SCOPE = 'se';

    /** Non-ambiguous action argument for settings command */
    const SETUP_UPGRADE_ACTION = 'up';

    /**
     * Checks if the execution scope is from Magento's setup:upgrade
     *
     * @param InputInterface $input
     * @return bool
     */
    public static function isCalledFromSetupUpgrade(InputInterface $input)
    {
        try {
            $parts = explode(':', $input->getFirstArgument());
            if (count($parts) !== 2) {
                return false;
            }
            list($commandScope, $commandAction) = $parts;
            $currentCommandScope = substr($commandScope, 0, strlen(self::SETUP_UPGRADE_SCOPE));
            $currentCommandAction = substr($commandAction, 0, strlen(self::SETUP_UPGRADE_ACTION));
            return (
                $currentCommandScope === self::SETUP_UPGRADE_SCOPE
                && $currentCommandAction === self::SETUP_UPGRADE_ACTION
            );
            // Exception will be thrown if InputInterface\Proxy is instantiated in non-cli context
        } catch (Exception $e) {
            return false;
        }
    }
}
