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

namespace Nosto\Tagging\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;
use Exception;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Util\Customer as CustomerUtil;

class PopulateCustomerReference implements DataPatchInterface, PatchVersionInterface
{
    /** @var ModuleDataSetupInterface */
    private ModuleDataSetupInterface $moduleDataSetup;

    /** @var CustomerCollectionFactory */
    private CustomerCollectionFactory $customerCollectionFactory;

    /** @var CustomerResource */
    private CustomerResource $customerResource;

    /** @var Logger */
    private Logger $logger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param CustomerResource $customerResource
     * @param Logger $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerCollectionFactory $customerCollectionFactory,
        CustomerResource $customerResource,
        Logger $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerResource = $customerResource;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->populateCustomerReference();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @throws LocalizedException
     * @throws Exception
     */
    public function populateCustomerReference()
    {
        $customerCollection = $this->customerCollectionFactory->create()
            ->addAttributeToSelect(NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME)
            ->setPageSize(1000);
        $iterator = new PagingIterator($customerCollection);
        /* @var Customer $customer */
        foreach ($iterator as $page) {
            foreach ($page as $customer) {
                if (!$customer->getData(NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME)) {
                    $customer->setData(
                        NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                        (new CustomerUtil)->generateCustomerReference($customer)
                    );
                    try {
                        $this->customerResource->saveAttribute(
                            $customer,
                            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
                        );
                    } catch (Exception $e) {
                        $this->logger->exception($e);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '6.0.3';
    }
}
