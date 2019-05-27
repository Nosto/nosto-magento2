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

namespace Nosto\Tagging\Model\Cart\Restore;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Nosto\Tagging\Api\Data\CustomerInterface;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Customer\CustomerFactory as NostoCustomerFactory;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Customer\Repository as NostoCustomerRepository;

class Builder
{
    private $logger;
    private $cookieManager;
    private $date;
    private $encryptor;
    private $nostoCustomerFactory;
    private $urlHelper;
    private $nostoCustomerRepository;

    /**
     * Builder constructor.
     * @param NostoLogger $logger
     * @param CookieManagerInterface $cookieManager
     * @param EncryptorInterface $encryptor
     * @param NostoCustomerFactory $nostoCustomerFactory
     * @param NostoCustomerRepository $nostoCustomerRepository
     * @param NostoHelperUrl $urlHelper
     * @param DateTime $date
     */
    public function __construct(
        NostoLogger $logger,
        CookieManagerInterface $cookieManager,
        EncryptorInterface $encryptor,
        NostoCustomerFactory $nostoCustomerFactory,
        NostoCustomerRepository $nostoCustomerRepository,
        NostoHelperUrl $urlHelper,
        DateTime $date
    ) {
        $this->logger = $logger;
        $this->cookieManager = $cookieManager;
        $this->encryptor = $encryptor;
        $this->date = $date;
        $this->nostoCustomerFactory = $nostoCustomerFactory;
        $this->urlHelper = $urlHelper;
        $this->nostoCustomerRepository = $nostoCustomerRepository;
    }

    /**
     * @param Quote $quote
     * @param Store $store
     * @return string|null
     */
    public function build(Quote $quote, Store $store)
    {
        $nostoCustomer = $this->updateNostoId($quote);
        if ($nostoCustomer && $nostoCustomer->getRestoreCartHash()) {
            return $this->generateRestoreCartUrl($nostoCustomer->getRestoreCartHash(), $store);
        }

        return null;
    }

    /**
     * @param Quote $quote
     *
     * @return CustomerInterface|null
     */
    private function updateNostoId(Quote $quote)
    {
        // Handle the Nosto customer & quote mapping
        $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);

        if ($quote === null || $nostoCustomerId === null || $quote->getId() === null) {
            return null;
        }
        $nostoCustomer = $this->nostoCustomerRepository->getOneByNostoIdAndQuoteId(
            $nostoCustomerId,
            $quote->getId()
        );

        if ($nostoCustomer instanceof CustomerInterface
            && $nostoCustomer->getCustomerId()
        ) {
            if ($nostoCustomer->getRestoreCartHash() === null) {
                $nostoCustomer->setRestoreCartHash($this->generateRestoreCartHash());
            }
            $nostoCustomer->setUpdatedAt($this->getNow());
        } else {
            /** @var \Nosto\Tagging\Model\Customer\Customer $nostoCustomer*/
            /** @noinspection PhpUndefinedMethodInspection */
            $nostoCustomer = $this->nostoCustomerFactory->create();
            $nostoCustomer->setQuoteId($quote->getId());
            $nostoCustomer->setNostoId($nostoCustomerId);
            $nostoCustomer->setCreatedAt($this->getNow());
            $nostoCustomer->setRestoreCartHash($this->generateRestoreCartHash());
        }
        try {
            $nostoCustomer = $this->nostoCustomerRepository->save($nostoCustomer);

            return $nostoCustomer;
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return null;
    }

    /**
     * Generate unique hash for restore cart
     * Size of it equals to or less than restore_cart_hash column length
     *
     * @return string
     */
    private function generateRestoreCartHash()
    {
        $hash = $this->encryptor->getHash(uniqid('nostocartrestore', true));
        if (strlen($hash) > NostoCustomer::NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH) {
            $hash = substr($hash, 0, NostoCustomer::NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH);
        }

        return $hash;
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
     * Returns restore cart url
     *
     * @param string $hash
     * @param Store $store
     * @return string the restore cart URL
     */
    private function generateRestoreCartUrl($hash, Store $store)
    {
        $params = $this->urlHelper->getUrlOptionsWithNoSid($store);
        $params['h'] = $hash;
        return $store->getUrl(NostoHelperUrl::NOSTO_PATH_RESTORE_CART, $params);
    }
}
