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

namespace Nosto\Tagging\Observer\Cart;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Quote\Model\Quote\Item;
use Nosto\Helper\SerializationHelper;
use Nosto\Object\Event\Cart\Update;
use Nosto\Operation\CartOperation;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;
use Nosto\Tagging\Model\Cart\Item\Builder as NostoCartItemBuilder;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;

/**
 * Class Add
 * @package Nosto\Tagging\Observer
 */
class Add implements ObserverInterface
{
    private $nostoHelperData;
    private $nostoHelperAccount;
    private $logger;
    private $moduleManager;
    private $nostoHelperScope;
    private $cookieManager;
    private $nostoCartItemBuilder;
    private $nostoCartBuilder;
    private $cookieMetadataFactory;
    const COOKIE_NAME = 'nosto.itemsAddedToCart';

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     * @param CookieManagerInterface $cookieManager
     * @param NostoCartItemBuilder $nostoCartItemBuilder
     * @param NostoCartBuilder $nostoCartBuilder
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $logger,
        ModuleManager $moduleManager,
        CookieManagerInterface $cookieManager,
        NostoCartItemBuilder $nostoCartItemBuilder,
        NostoCartBuilder $nostoCartBuilder,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->cookieManager = $cookieManager;
        $this->nostoCartItemBuilder = $nostoCartItemBuilder;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Event handler for the "checkout_cart_product_add_after" and  event.
     * Sends a cart update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @suppress PhanDeprecatedFunction
     */
    public function execute(Observer $observer)
    {
        try {
            if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
                $nostoAccount = $this->nostoHelperAccount->findAccount(
                    $this->nostoHelperScope->getStore()
                );

                if (!$nostoAccount || !$nostoAccount->isConnectedToNosto()) {
                    return;
                }

                HttpRequest::buildUserAgent(
                    'Magento',
                    $this->nostoHelperData->getPlatformVersion(),
                    $this->nostoHelperData->getModuleVersion()
                );

                $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
                if (!$nostoCustomerId) {
                    $this->logger->info('Cannot find customer id from cookie.');
                    return;
                }

                /** @noinspection PhpUndefinedMethodInspection */
                $quoteItem = $observer->getQuoteItem();
                if (!$quoteItem instanceof Item) {
                    $this->logger->info('Cannot find quote item from the event.');
                    return;
                }

                $store = $this->nostoHelperScope->getStore();
                $cartUpdate = new Update();
                $addedItem = $this->nostoCartItemBuilder->build(
                    $quoteItem,
                    $store->getCurrentCurrencyCode() ?: $store->getDefaultCurrencyCode()
                );
                $cartUpdate->setAddedItems([$addedItem]);

                if (!headers_sent()) {
                    //use the cookie way
                    $metadata = $this->cookieMetadataFactory
                        ->createPublicCookieMetadata()
                        ->setDuration(60)
                        ->setSecure(false)
                        ->setHttpOnly(false)
                        ->setPath('/');
                    $this->cookieManager->setPublicCookie(
                        self::COOKIE_NAME,
                        SerializationHelper::serialize($cartUpdate),
                        $metadata
                    );
                } else {
                    $this->logger->info('Headers sent already. Cannot set the cookie.');
                }

                if ($this->nostoHelperData->isSendAddToCartEventEnabled()) {
                    //use the message way
                    $quote = $quoteItem->getQuote();
                    if ($quote instanceof \Magento\Quote\Model\Quote) {
                        $nostoCart = $this->nostoCartBuilder->build(
                            $quote,
                            $store
                        );
                        $cartUpdate->setCart($nostoCart);
                    } else {
                        $this->logger->info('Cannot find quote from the event.');
                    }

                    $cartOperation = new CartOperation($nostoAccount);
                    $cartOperation->updateCart($cartUpdate, $nostoCustomerId, $nostoAccount->getName());
                }
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }
}
