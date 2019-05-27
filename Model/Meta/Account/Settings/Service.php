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

namespace Nosto\Tagging\Model\Meta\Account\Settings;

use Exception;
use Magento\Store\Model\Store;
use Nosto\Operation\UpdateSettings;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Account\Settings\Builder as NostoSettingsBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Service
{
    private $logger;
    private $nostoHelperAccount;
    private $nostoSettingsBuilder;

    /**
     * @param NostoLogger $logger
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoSettingsBuilder $nostoSettingsBuilder
     */
    public function __construct(
        NostoLogger $logger,
        NostoHelperAccount $nostoHelperAccount,
        NostoSettingsBuilder $nostoSettingsBuilder
    ) {
        $this->logger = $logger;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoSettingsBuilder = $nostoSettingsBuilder;
    }

    /**
     * Sends a account settings update request to Nosto via the API.
     *
     * @param Store $store the store for which the settings are to be updated.
     * @return bool a boolean value indicating whether the operation was successful
     */
    public function update(Store $store)
    {
        if ($account = $this->nostoHelperAccount->findAccount($store)) {
            $settings = $this->nostoSettingsBuilder->build($store);

            try {
                $service = new UpdateSettings($account);
                return $service->update($settings);
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        } else {
            $this->logger->info(
                'Skipping update; an account doesn\'t exist for ' .
                $store->getName()
            );
        }

        return false;
    }
}
