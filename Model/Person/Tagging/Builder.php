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

namespace Nosto\Tagging\Model\Person\Tagging;

use DateTime;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Nosto\Model\Customer;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Email\Repository as NostoEmailRepository;
use Nosto\Tagging\Model\Person\Builder as PersonBuilder;
use Nosto\Tagging\Util\Customer as CustomerUtil;

/**
 * Builder class for buyer
 */
class Builder extends PersonBuilder
{

    public const GENDER_MALE = 'Male';
    public const GENDER_FEMALE = 'Female';
    public const GENDER_MALE_ID = '1';
    public const GENDER_FEMALE_ID = '2';

    private GroupRepository $groupRepository;
    private CustomerRepositoryInterface $customerRepository;
    private NostoLogger $logger;

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
     * @inheritDoc
     * @return Customer
     */
    public function buildObject(
        string $firstName,
        string $lastName,
        string $email,
        string $phone = null,
        string $postCode = null,
        string $country = null,
        string $customerGroup = null,
        string $dateOfBirth = null,
        string $gender = null,
        string $customerReference = null
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
            $customer->setDateOfBirth(DateTime::createFromFormat('Y-m-d', $dateOfBirth));
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
            
            // Check for null values and provide defaults for required string parameters
            $firstName = $customer->getFirstname() ?: '';
            $lastName = $customer->getLastname() ?: '';
            $email = $customer->getEmail() ?: '';

            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $this->build(
                $firstName,
                $lastName,
                $email,
                null,
                null,
                null,
                $customerGroup,
                $customer->getDob(),
                $gender,
                $customerReference
            );
        } catch (Exception $e) {
            $this->logger->exception($e);
            return null;
        }
    }

    /**
     * @param CustomerInterface $customer
     * @return string|null
     */
    private function getCustomerGroupName(CustomerInterface $customer)
    {
        $groupId = (int)$customer->getGroupId();
        try {
            return $this->groupRepository->getById($groupId)->getCode();
        } catch (Exception $e) {
            return null;
        }
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
    private function getCustomerReference(CurrentCustomer $currentCustomer): string
    {
        $customerReference = '';

        try {
            $customer = $currentCustomer->getCustomer();
            $customerReference = $customer->getCustomAttribute(
                NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
            );

            if ($customerReference === null) {
                $customerUtil = new CustomerUtil();
                $customerReference = $customerUtil->generateCustomerReference($customer);
                $customer->setCustomAttribute(
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                    $customerReference
                );
                $this->customerRepository->save($customer);
                return $customerReference;
            }
            return $customerReference->getValue();
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        return $customerReference;
    }
}
