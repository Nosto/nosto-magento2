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

namespace Nosto\Tagging\CustomerData;

use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Nosto\Object\Cart\LineItem;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\CustomerFactory as NostoCustomerFactory;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Model\Cart\Restore\Builder as NostoRestoreCartUrlBuilder;
use Psr\Log\LoggerInterface;

class CartTagging extends HashedTagging implements SectionSourceInterface
{
    private $cartHelper;
    private $cookieManager;
    private $logger;
    private $date;
    private $scopeHelper;
    private $urlHelper;
    private $quote = null;
    private $nostoCartBuilder;
    private $nostoRestoreCartUrlBuilder;
    private $nostoCustomerFactory;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @param CartHelper $cartHelper
     * @param NostoCartBuilder $nostoCartBuilder
     * @param NostoHelperScope $scopeHelper
     * @param CookieManagerInterface $cookieManager
     * @param LoggerInterface $logger
     * @param DateTime $date
     * @param NostoCustomerFactory $nostoCustomerFactory
     */
    public function __construct(
        CartHelper $cartHelper,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger,
        DateTime $date,
        NostoCartBuilder $nostoCartBuilder,
        NostoHelperScope $scopeHelper,
        NostoHelperUrl $urlHelper,
        /** @noinspection PhpUndefinedClassInspection */
        NostoCustomerFactory $nostoCustomerFactory,
        NostoRestoreCartUrlBuilder $nostoRestoreCartUrlBuilder
    ) {
        $this->cartHelper = $cartHelper;
        $this->logger = $logger;
        $this->date = $date;
        $this->cookieManager = $cookieManager;
        $this->scopeHelper = $scopeHelper;
        $this->urlHelper = $urlHelper;
        $this->nostoCustomerFactory = $nostoCustomerFactory;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->nostoRestoreCartUrlBuilder = $nostoRestoreCartUrlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        $data = [
            'hcid' => parent::generateVisitorChecksum($nostoCustomerId),
            "items" => [],
            "itemCount" => 0,
            'restore_cart_url' => ''
        ];
        $cart = $this->cartHelper->getCart();
        $nostoCart = $this->nostoCartBuilder->build(
            $this->getQuote(),
            $this->scopeHelper->getStore()
        );
        $itemCount = $cart->getItemsCount();
        $data["itemCount"] = $itemCount;
        $addedCount = 0;
        /* @var LineItem $item */
        foreach ($nostoCart->getItems() as $item) {
            $addedCount++;
            $data["items"][] = [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'name' => $item->getName(),
                'unit_price' => $item->getUnitPrice(),
                'price_currency_code' => $item->getPriceCurrencyCode(),
                'total_count' => $itemCount,
                'index' => $addedCount
            ];
        }

        if ($data["itemCount"] > 0) {
            $store = $this->scopeHelper->getStore();
            $data['restore_cart_url'] = $this->nostoRestoreCartUrlBuilder
                ->build($this->getQuote(), $store);
        }

        return $data;
    }

    /**
     * Get active quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        if (!$this->quote) {
            $cart = $this->cartHelper->getCart();
            $this->quote = $cart->getQuote();
        }

        return $this->quote;
    }

    /**
     * Return customer quote items
     *
     * @return \Magento\Quote\Model\Quote\Item[]
     */
    public function getAllQuoteItems()
    {
        return $this->getQuote()->getAllVisibleItems();
    }
}
