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
use Nosto\Object\Cart\LineItem;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;
use Nosto\Tagging\Model\Customer;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\CustomerFactory as NostoCustomerFactory;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

class CartTagging extends HashedTagging implements SectionSourceInterface
{
    private $cartHelper;
    private $nostoCartBuilder;
    private $cookieManager;
    private $nostoCustomerFactory;
    private $quote = null;
    private $nostoHelperSentry;
    private $date;
    private $nostoHelperScope;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @param CartHelper $cartHelper
     * @param NostoCartBuilder $nostoCartBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param CookieManagerInterface $cookieManager
     * @param NostoHelperSentry $nostoHelperSentry
     * @param DateTime $date
     * @param NostoCustomerFactory $nostoCustomerFactory
     */
    public function __construct(
        CartHelper $cartHelper,
        NostoCartBuilder $nostoCartBuilder,
        NostoHelperScope $nostoHelperScope,
        CookieManagerInterface $cookieManager,
        NostoHelperSentry $nostoHelperSentry,
        DateTime $date,
        /** @noinspection PhpUndefinedClassInspection */
        NostoCustomerFactory $nostoCustomerFactory
    ) {
        $this->cartHelper = $cartHelper;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->date = $date;
        $this->cookieManager = $cookieManager;
        $this->nostoCustomerFactory = $nostoCustomerFactory;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperSentry = $nostoHelperSentry;
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
        ];
        $cart = $this->cartHelper->getCart();
        $nostoCart = $this->nostoCartBuilder->build(
            $this->getQuote(),
            $this->nostoHelperScope->getStore()
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
            $this->updateNostoId();
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
     * @suppress PhanDeprecatedFunction
     */
    private function updateNostoId()
    {
        // Handle the Nosto customer & quote mapping
        $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        $quoteId = $this->getQuote()->getId();
        if (!empty($quoteId) && !empty($nostoCustomerId)) {
            /** @noinspection PhpUndefinedMethodInspection */
            $customerQuery = $this->nostoCustomerFactory
                ->create()
                ->getCollection()
                ->addFieldToFilter(NostoCustomer::QUOTE_ID, $quoteId)
                ->addFieldToFilter(NostoCustomer::NOSTO_ID, $nostoCustomerId)
                ->setPageSize(1)
                ->setCurPage(1);

            /** @var Customer $nostoCustomer */
            $nostoCustomer = $customerQuery->getFirstItem(); // @codingStandardsIgnoreLine
            if ($nostoCustomer->hasData(NostoCustomer::CUSTOMER_ID)) {
                $nostoCustomer->setUpdatedAt(self::getNow());
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer = $this->nostoCustomerFactory->create();
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setQuoteId($quoteId);
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setNostoId($nostoCustomerId);
                $nostoCustomer->setCreatedAt(self::getNow());
            }
            try {
                /** @noinspection PhpDeprecationInspection */
                $nostoCustomer->save();
            } catch (\Exception $e) {
                $this->nostoHelperSentry->error($e);
            }
        }
    }

    /**
     * Returns the current datetime object
     *
     * @return \DateTime the current datetime
     */
    private function getNow()
    {
        return \DateTime::createFromFormat('Y-m-d H:i:s', $this->date->date());
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
