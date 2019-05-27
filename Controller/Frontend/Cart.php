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

namespace Nosto\Tagging\Controller\Frontend;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Customer\Repository as NostoCustomerRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/*
 * Controller class for handling cart restoration
 */
class Cart extends Action
{
    /**
     * The name of the hash parameter to look from URL
     */
    const HASH_PARAM = 'h';

    private $context;
    private $moduleManager;
    private $checkoutSession;
    private $logger;
    private $nostoUrlHelper;
    private $nostoScopeHelper;
    private $nostoCustomerRepository;
    private $cartRepository;

    /**
     * Cart constructor.
     * @param Context $context
     * @param ModuleManager $moduleManager
     * @param Session $checkoutSession
     * @param NostoLogger $logger
     * @param NostoHelperUrl $nostoUrlHelper
     * @param NostoHelperScope $nostoScopeHelper
     * @param NostoCustomerRepository $nostoCustomerRepository
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        Context $context,
        ModuleManager $moduleManager,
        Session $checkoutSession,
        NostoLogger $logger,
        NostoHelperUrl $nostoUrlHelper,
        NostoHelperScope $nostoScopeHelper,
        NostoCustomerRepository $nostoCustomerRepository,
        CartRepositoryInterface $cartRepository
    ) {
        parent::__construct($context);
        $this->context = $context;
        $this->moduleManager = $moduleManager;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->nostoUrlHelper = $nostoUrlHelper;
        $this->nostoScopeHelper = $nostoScopeHelper;
        $this->nostoCustomerRepository = $nostoCustomerRepository;
        $this->cartRepository = $cartRepository;
    }

    public function execute()
    {
        $store = $this->nostoScopeHelper->getStore();
        $redirectUrl = $store->getBaseUrl();

        $url = $this->context->getUrl();
        $currentUrl = $url->getCurrentUrl();

        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            if (!$this->checkoutSession->getQuoteId()) {
                $restoreCartHash = $this->getRequest()->getParam(self::HASH_PARAM);
                try {
                    if ($restoreCartHash) {
                        $quote = $this->resolveQuote($restoreCartHash);
                        if ($quote !== null) {
                            $this->checkoutSession->setQuoteId($quote->getId());
                            $redirectUrl = $this->nostoUrlHelper->getUrlCart($store, $currentUrl);
                        } else {
                            throw new NostoException('Could not resolve quote for the given restore cart hash');
                        }
                    } else {
                        throw new NostoException('No hash provided for restore cart');
                    }
                } catch (\Exception $e) {
                    $this->logger->exception($e);
                    $this->messageManager->addErrorMessage('Sorry, we could not find your cart');
                }
            } else {
                $redirectUrl = $this->nostoUrlHelper->getUrlCart($store, $currentUrl);
            }
        }
        return $this->_redirect($redirectUrl);
    }

    /**
     * Resolves the cart (quote) by the given hash
     *
     * @param $restoreCartHash
     * @return CartInterface|null
     * @throws NostoException
     * @throws NoSuchEntityException
     */
    private function resolveQuote($restoreCartHash)
    {
        $customer = $this->nostoCustomerRepository->getOneByRestoreCartHash($restoreCartHash);
        if ($customer === null || !$customer->getCustomerId()) {
            throw new NostoException(
                sprintf(
                    'No nosto customer found for hash %s',
                    $restoreCartHash
                )
            );
        }

        if ($customer->getQuoteId() === null) {
            throw new NostoException(
                sprintf(
                    'Found customer without quote for hash %s',
                    $restoreCartHash
                )
            );
        }

        $quote = $this->cartRepository->get($customer->getQuoteId());
        if ($quote === null || !$quote->getId()) {
            throw new NostoException(
                sprintf(
                    'No quote found for id %d',
                    $customer->getQuoteId()
                )
            );
        }
        // Note - we reactivate the cart if it's not active.
        // This would happen for example when the cart was bought.
        if (!$quote->getIsActive()) {
            $quote->setIsActive(true);

            $this->cartRepository->save($quote);
        }

        return $quote;
    }
}
