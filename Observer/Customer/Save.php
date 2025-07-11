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

namespace Nosto\Tagging\Observer\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Util\Customer as CustomerUtil;

class Save implements ObserverInterface
{
    /** @var ModuleManager $moduleManger */
    private ModuleManager $moduleManger;

    /** @var Logger $logger */
    private Logger $logger;

    /** @var CustomerRepositoryInterface $customerRepository */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * Save constructor.
     *
     * @param ModuleManager $moduleManger
     * @param Logger $logger
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        ModuleManager $moduleManger,
        Logger $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->moduleManger = $moduleManger;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        if ($this->moduleManger->isEnabled(NostoHelperData::MODULE_NAME)) {
            /** @var Customer $customer */
            /** @noinspection PhpUndefinedMethodInspection */
            $customer = $observer->getCustomer();
            try {
                $customerModel = $this->customerRepository->getById($customer->getId());
                $customerReference = $customerModel->getCustomAttribute(
                    NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
                );

                if ($customerReference === null) {
                    $customerUtil = new CustomerUtil();
                    $customerReference = $customerUtil->generateCustomerReference($customer);
                    $customer->setData(
                        NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                        $customerReference
                    );
                }
            } catch (NoSuchEntityException $e) {
                $this->logger->error(sprintf(
                    'Unable to find customer with ID: %s, Error: %s',
                    $customer->getId(),
                    $e->getMessage()
                ));
            }
        }
    }
}
