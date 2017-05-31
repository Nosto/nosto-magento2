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

namespace Nosto\Tagging\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order;
use Nosto\Operation\OrderConfirm;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\CustomerFactory;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

/**
 * Class Save
 * @package Nosto\Tagging\Observer
 */
class Save implements ObserverInterface
{
    private $nostoHelperData;
    private $nostoHelperAccount;
    private $nostoHelperSentry;
    private $nostoOrderBuilder;
    private $moduleManager;
    private $customerFactory;
    private $nostoHelperScope;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperSentry $nostoHelperSentry
     * @param ModuleManager $moduleManager
     * @param CustomerFactory $customerFactory
     * @param NostoOrderBuilder $orderBuilder
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperSentry $nostoHelperSentry,
        ModuleManager $moduleManager,
        /** @noinspection PhpUndefinedClassInspection */
        CustomerFactory $customerFactory,
        NostoOrderBuilder $orderBuilder
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperSentry = $nostoHelperSentry;
        $this->moduleManager = $moduleManager;
        $this->nostoOrderBuilder = $orderBuilder;
        $this->customerFactory = $customerFactory;

        HttpRequest::buildUserAgent(
            'Magento',
            $nostoHelperData->getPlatformVersion(),
            $nostoHelperData->getModuleVersion()
        );
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * Event handler for the "catalog_product_save_after" and  event.
     * Sends a product update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @suppress PhanDeprecatedFunction
     */
    public function execute(Observer $observer)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            /* @var Order $order */
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $observer->getOrder();
            $nostoOrder = $this->nostoOrderBuilder->build($order);
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $this->nostoHelperScope->getStore()
            );
            if ($nostoAccount !== null) {
                $quoteId = $order->getQuoteId();
                /** @var NostoCustomer $nostoCustomer */
                /** @noinspection PhpDeprecationInspection */
                $nostoCustomer = $this->customerFactory
                    ->create()
                    ->load($quoteId, NostoCustomer::QUOTE_ID);

                $orderService = new OrderConfirm($nostoAccount);
                try {
                    $orderService->send($nostoOrder, $nostoCustomer->getNostoId());
                } catch (\Exception $e) {
                    $this->nostoHelperSentry->error(
                        sprintf(
                            "Failed to save order with quote #%s for customer #%s.
                        Message was: %s",
                            $quoteId,
                            $nostoCustomer->getNostoId(),
                            $e->getMessage()
                        )
                    );
                }
            }
        }
    }
}
