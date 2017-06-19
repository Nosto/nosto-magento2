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

namespace Nosto\Tagging\Controller\Frontend;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\Data\CartInterface;
use Nosto\Tagging\Api\Data\CustomerInterface;
use Nosto\Tagging\Helper\Url;
use Nosto\Tagging\Helper\Scope;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\CustomerFactory;
use Nosto\Tagging\Model\Customer;
use Nosto\NostoException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as ResourceQuote;
use Psr\Log\LoggerInterface;


class RestoreCart extends Action
{
    /**
     * The name of the hash parameter to look from URL
     */
    const HASH_PARAM = 'h';

    private $urlHelper;
    private $scope;
    private $context;
    private $moduleManager;
    private $checkoutSession;
    private $customerFactory;
    private $quoteFactory;
    private $quoteResource;
    private $logger;

    public function __construct(
        Context $context,
        Url $urlHelper,
        ModuleManager $moduleManager,
        Session $checkoutSession,
        CustomerFactory $customerFactory,
        Scope $scope,
        QuoteFactory $quoteFactory,
        ResourceQuote $quoteResource,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->urlHelper = $urlHelper;
        $this->scope = $scope;
        $this->context = $context;
        $this->moduleManager = $moduleManager;
        $this->checkoutSession = $checkoutSession;
        $this->customerFactory = $customerFactory;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResource = $quoteResource;
        $this->logger = $logger;
    }

    public function execute()
    {
        $store = $this->scope->getStore();
        $storeId = $this->getRequest()->getParam('___store');

        $baseUrl = $this->scope->getStore($storeId)->getBaseUrl();
        $redirectUrl = $baseUrl;

        $url = $this->context->getUrl();
        $currentUrl = $url->getCurrentUrl();

        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            if (!$this->checkoutSession->getQuoteId()) {
                $restoreCartHash = $this->getRequest()->getParam(self::HASH_PARAM);
                if (!$restoreCartHash) {
                    throw new NostoException('No hash provided for restore cart');
                } else {
                    try {
                        $quote = $this->resolveQuote($restoreCartHash);
                        $this->checkoutSession->setQuoteId($quote->getId());
                        $redirectUrl = $this->urlHelper->getUrlCart($store, $currentUrl);
                    } catch (\Exception $e) {
                        $this->logger->error($e->__toString());
                        $this->messageManager->addErrorMessage('Sorry, we could not find your cart');
                    }
                }
            } else {
                $redirectUrl = $this->urlHelper->getUrlCart($store, $currentUrl);
            }
        }

        $this->_redirect($redirectUrl);
    }

    /**
     * Resolves the cart (quote) by the given hash
     *
     * @param $restoreCartHash
     * @return Quote|null
     * @throws NostoException
     */
    protected function resolveQuote($restoreCartHash)
    {
        $customerQuery = $this->customerFactory
            ->create()
            ->getCollection()
            ->addFieldToFilter(CustomerInterface::RESTORE_CART_HASH, $restoreCartHash)
            ->setPageSize(1)
            ->setCurPage(1);

        /** @var Customer $nostoCustomer */
        $nostoCustomer = $customerQuery->getFirstItem();
        if ($nostoCustomer == null || !$nostoCustomer->hasData() || $nostoCustomer->getQuoteId() === null) {
            throw new NostoException(
                sprintf(
                    'No nosto customer found for hash %s',
                    $restoreCartHash
                )
            );
        }

        $quoteId = $nostoCustomer->getQuoteId();

        /** @var  $quoteCollection */
        $quoteCollection = $this->quoteFactory->create()->getCollection();
        $quoteCollection->addFieldToFilter(CartInterface::KEY_ENTITY_ID, $quoteId)
            ->setPageSize(1)
            ->setCurPage(1);

        /** @var Quote $quote */
        $quote = $quoteCollection->getFirstItem();
        if ($quote == null || !$quote->hasData()) {
            throw new NostoException(
                sprintf(
                    'No quote found for id %d',
                    $quoteId
                )
            );
        }
        // Note - we reactivate the cart if it's not active.
        // This would happen for example when the cart was bought.
        if (!$quote->getIsActive()) {
            $quote->setIsActive(1);
            $this->quoteResource->save($quote);
        }

        return $quote;
    }
}
