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

namespace Nosto\Tagging\Observer\Customer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Newsletter\Model\Subscriber;
use Nosto\Operation\MarketingPermission;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class UpdateMarketingPermission implements ObserverInterface
{
    private NostoHelperAccount $nostoHelperAccount;
    private NostoLogger $logger;
    private ModuleManager $moduleManager;
    private NostoHelperScope $nostoHelperScope;

    /**
     * UpdateMarketingPermission constructor.
     *
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $logger,
        ModuleManager $moduleManager
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Event handler for the "newsletter_subscriber_save_commit_after" event.
     * Sends a customer update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        /** @var Subscriber $subscriber */
        $subscriber = $observer->getEvent()->getSubscriber();
        $currentStore = $this->nostoHelperScope->getStore();
        $stores = $currentStore->getWebsite()->getStores();
        if (!$subscriber instanceof Subscriber
            || !$this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)
            || $stores === []
        ) {
            return;
        }
        foreach ($stores as $store) {
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $store
            );
            if ($nostoAccount === null) {
                continue;
            }
            $operation = new MarketingPermission($nostoAccount);
            $isSubscribed = $subscriber->getSubscriberStatus() === Subscriber::STATUS_SUBSCRIBED;
            try {
                $operation->update(
                    $subscriber->getSubscriberEmail(),
                    $isSubscribed
                );
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf(
                        "Failed to update customer marketing permission.
                        Message was: %s",
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
