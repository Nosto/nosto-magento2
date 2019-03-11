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

namespace Nosto\Tagging\Model\Admin\Notification;

use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\Phrase;

class Messages implements MessageInterface
{
    private $nostoHelperScope;
    private $nostoHelperAccount;
    private $message;
    private $display;

    /**
     * Messages constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope
    ) {
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    /**
     * @return Phrase|string
     */
    public function getText()
    {
        return __($this->message);
    }

    /**
     * @return string
     */
    public function getIdentity()
    {
        //@codingStandardsIgnoreLine
        return md5('Nosto_Account_Notification');
    }

    /**
     * @return bool
     */
    public function isDisplayed()
    {
        $stores = $this->nostoHelperScope->getStores();
        $storeNames = [];

        foreach ($stores as $store) {
            //Check if the store is connected to Nosto
            if ($this->nostoHelperAccount->findAccount($store)
                && $this->nostoHelperAccount->isDomainValid($store) === false) {
                    $storeNames[] = $store->getName();
                    $this->display = true;
            }
        }

        if ($this->display === true) {
            $this->buildMessage($storeNames);
            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    public function getSeverity()
    {
        return MessageInterface::SEVERITY_CRITICAL;
    }

    /**
     * Set the value of the message
     */
    private function buildMessage($storeNames)
    {
        $message = 'Nosto account is invalid for the stores: ';

        foreach ($storeNames as $storeName) {
            $message .= ' * ' .$storeName;
        }

        $this->message = __($message);
    }
}
