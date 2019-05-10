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

namespace Nosto\Tagging\Model\Order\Buyer;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Nosto\Object\AbstractPerson;
use Nosto\Object\Order\Buyer;
use Nosto\Tagging\Model\Person\Builder as PersonBuilder;

/**
 * Builder class for buyer
 * @package Nosto\Tagging\Model\Order\Buyer
 */
class Builder extends PersonBuilder
{
    /**
     * @inheritdoc
     * @return Buyer
     */
    public function buildObject( // @codingStandardsIgnoreLine
        $firstName,
        $lastName,
        $email,
        $phone = null,
        $postCode = null,
        $country = null,
        $customerGroup = null,
        $dateOfBirth = null,
        $gender = null,
        $customerReference = null
    ) {
        $buyer = new Buyer();
        $buyer->setFirstName($firstName);
        $buyer->setLastName($lastName);
        $buyer->setEmail($email);
        $buyer->setPhone($phone);
        $buyer->setPostCode($postCode);
        $buyer->setCountry($country);

        return $buyer;
    }

    /**
     * Builds buyer from the order
     *
     * @param Order $order
     * @return AbstractPerson|null
     * @suppress PhanTypeMismatchArgument
     */
    public function fromOrder(Order $order)
    {
        $address = $order->getBillingAddress();
        $telephone = null;
        $postcode  = null;
        $countryId =  null;
        if ($address instanceof OrderAddressInterface) {
            $telephone = $address->getTelephone() ? (string)$address->getTelephone() : null;
            $postcode = $address->getPostcode() ? (string)$address->getPostcode() : null;
            $countryId = $address->getCountryId() ? (string)$address->getCountryId() : null;
        }
        $customerFirstname = $order->getCustomerFirstname() ? (string)$order->getCustomerFirstname() : '';
        $customerLastname = $order->getCustomerLastname() ? (string)$order->getCustomerLastname() : '';
        $customerEmail = $order->getCustomerEmail() ? (string)$order->getCustomerEmail(): '';
        $buyer = $this->build(
            $customerFirstname,
            $customerLastname,
            $customerEmail,
            $telephone,
            $postcode,
            $countryId
        );

        return $buyer;
    }
}
