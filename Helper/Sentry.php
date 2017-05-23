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

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\DirectoryList;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

/**
 * NostoHelperData helper used for common tasks, mainly configurations.
 */
class Sentry extends AbstractHelper
{
    private $nostoHelperData;
    private $nostoHelperScope;
    private $directoryList;
    private $nostoHelperAccount;

    /**
     * Sentry constructor.
     * @param Context $context
     * @param DirectoryList $directoryList
     * @param Data $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(
        Context $context,
        DirectoryList $directoryList,
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope
    ) {
        parent::__construct($context);

        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->directoryList = $directoryList;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    public function error(\Exception $e)
    {
        $this->_logger->error($e->getTraceAsString());
        if ($this->nostoHelperData->isSentryUpdatesEnabled()) {
            $store = $this->nostoHelperScope->getStore(true);

            $client = new \Raven_Client('https://22ea9e5f70404157bf4f81e420d35bf1:23a0c572164748f7aafd9659d396a144@sentry.io/169186');
            $client->setPrefixes(array($this->directoryList->getRoot()));
            $client->setRelease($this->nostoHelperData->getModuleVersion());
            $client->setEnvironment($this->nostoHelperData->getPlatformVersion());

            $client->tags_context(array('php' => phpversion()));
            $client->user_context(
                array(
                    'merchant_id' => $this->nostoHelperAccount->findAccount($store)->getName(),
                    'default_currency' => $store->getDefaultCurrencyCode(),
                    'base_currency' => $store->getBaseCurrencyCode(),
                    'base_url' => $store->getBaseUrl(),
                    'store_code' => $store->getCode()
                )
            );
            $client->exception($e);
        }
    }
}
