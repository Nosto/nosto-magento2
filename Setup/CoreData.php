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

namespace Nosto\Tagging\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Util\Customer as CustomerUtil;
use Nosto\Tagging\Util\PagingIterator;
use Zend_Validate_Exception;

abstract class CoreData
{

    /** @var CustomerSetupFactory */
    private $customerSetupFactory;

    /** @var AttributeSetFactory */
    private $attributeSetFactory;

    private $customerReferenceForms = ['adminhtml_customer'];

    /** @var CustomerFactory */
    private $customerCollectionFactory;

    /** @var CustomerResource */
    private $customerResource;

    /**
     * CoreData constructor.
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param CustomerResource $customerResource
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        CustomerCollectionFactory $customerCollectionFactory,
        CustomerResource $customerResource
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerResource = $customerResource;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function addCustomerReference(ModuleDataSetupInterface $setup)
    {
        $customerEavSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerEavSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = (int)$customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $customerEavSetup->addAttribute(
            Customer::ENTITY,
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
            [
                'type' => 'varchar',
                'label' => 'Nosto Customer Reference',
                'input' => 'text',
                'required' => false,
                'sort_order' => 120,
                'position' => 120,
                'visible' => true,
                'user_defined' => true,
                'unique' => true,
                'system' => false,
            ]
        );

        $attribute = $customerEavSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
        );

        $attribute->addData(
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => $this->customerReferenceForms,
            ]
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        /** @noinspection PhpDeprecationInspection */
        $attribute->save();
    }

    /**
     * Sets the attribute Nosto customer reference to be only editable in admin
     *
     * @param ModuleDataSetupInterface $setup
     * @throws LocalizedException
     */
    public function alterCustomerReferenceNonEditable(ModuleDataSetupInterface $setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $attribute = $customerSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
        );
        $attribute->addData(
            [
                'used_in_forms' => $this->customerReferenceForms
            ]
        );
        $attribute->save();
    }

    /**
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Nosto\NostoException
     */
    public function populateCustomerReference()
    {
        $customerCollection = $this->customerCollectionFactory->create()
        ->addAttributeToSelect('*')
        ->setPageSize(1000);
        $iterator = new PagingIterator($customerCollection);
        /* @var Customer $customer */
        foreach ($iterator as $page) {
            foreach ($page as $customer) {
                if (!$customer->getCustomAttribute(NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME)) {
                    $customer->setData(
                        NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                        CustomerUtil::generateCustomerReference($customer)
                    );
                    $this->customerResource->save($customer); // @codingStandardsIgnoreLine
                }
            }
        }
    }
}
