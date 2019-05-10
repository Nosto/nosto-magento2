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

namespace Nosto\Tagging\Model\Person\Tagging;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Nosto\Object\Customer;
use Magento\Customer\Api\Data\CustomerInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Email\Repository as NostoEmailRepository;
use Nosto\Tagging\Model\Person\Builder as PersonBuilder;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\CustomerData\HashedTagging;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Nosto\Tagging\Logger\Logger as NostoLogger;

/**
 * Builder class for buyer
 * @package Nosto\Tagging\Model\Order\Buyer
 */
class Builder extends PersonBuilder
{

    const GENDER_MALE = 'Male';
    const GENDER_FEMALE = 'Female';
    const GENDER_MALE_ID = '1';
    const GENDER_FEMALE_ID = '2';

    private $groupRepository;
    private $customerRepository;
    private $logger;

    /**
     * Builder constructor.
     * @param GroupRepository $groupRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param NostoEmailRepository $emailRepository
     * @param NostoLogger $logger
     * @param EventManager $eventManager
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        GroupRepository $groupRepository,
        CustomerRepositoryInterface $customerRepository,
        NostoEmailRepository $emailRepository,
        NostoLogger $logger,
        EventManager $eventManager,
        NostoHelperData $nostoHelperData
    ) {
        $this->groupRepository = $groupRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        parent::__construct($emailRepository, $eventManager, $nostoHelperData);
    }

    /**
     * @inheritdoc
     * @return Customer
     */
    public function buildObject(
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
        $customer = new Customer();
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setEmail($email);
        $customer->setPhone($phone);
        $customer->setPostCode($postCode);
        $customer->setCountry($country);
        $customer->setCustomerGroup($customerGroup);
        $customer->setCustomerReference($customerReference);
        $customer->setGender($gender);
        if ($dateOfBirth !== null) {
            $customer->setDateOfBirth(\DateTime::createFromFormat('Y-m-d', $dateOfBirth));
        }

        return $customer;
    }

    /**
     * Builds person from the current session / logged in user
     *
     * @param CurrentCustomer $currentCustomer
     * @return Customer|null
     */
    public function fromSession(CurrentCustomer $currentCustomer)
    {
        try {
            $customer = $currentCustomer->getCustomer();
            $customerGroup = $this->getCustomerGroupName($customer);
            $gender = $this->getGenderName($customer);
            $customerReference = $this->getCustomerReference($currentCustomer);

            $person = $this->build(
                $customer->getFirstname(),
                $customer->getLastname(),
                $customer->getEmail(),
                null,
                null,
                null,
                $customerGroup,
                $customer->getDob(),
                $gender,
                $customerReference
            );
            /** @var $person Customer */
            return $person;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param CustomerInterface $customer
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCustomerGroupName(CustomerInterface $customer)
    {
        $groupId = (int)$customer->getGroupId();
        if ($groupId === null) {
            return null;
        }
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    /**
     * @param CustomerInterface $customer
     * @return null|string
     */
    private function getGenderName(CustomerInterface $customer)
    {
        $gender = $customer->getGender();
        switch ($gender) {
            case self::GENDER_MALE_ID:
                return self::GENDER_MALE;
            case self::GENDER_FEMALE_ID:
                return self::GENDER_FEMALE;
            default:
                return null;
        }
    }

    /**
     * @param CurrentCustomer $currentCustomer
     * @return string
     */
    private function getCustomerReference(CurrentCustomer $currentCustomer)
    {
        $customerReference = '';

        try {
            $customer = $currentCustomer->getCustomer();
            $customerReference = $customer->getCustomAttribute(
                NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
            );

            if ($customerReference === null) {
                $customerReference = HashedTagging::generateVisitorChecksum(
                    $currentCustomer->getCustomerId() . $customer->getEmail()
                );
                $customer->setCustomAttribute(
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                    $customerReference
                );
                $this->customerRepository->save($customer);
                return $customerReference;
            }
            return $customerReference->getValue();
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        return $customerReference;
    }
}
