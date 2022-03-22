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
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Zend_Validate_Exception;
use Exception;

class AddCustomerReference implements DataPatchInterface, PatchVersionInterface
{
    private $customerReferenceForms = ['adminhtml_customer'];
    /** @var CustomerSetupFactory */
    private $customerSetupFactory;

    /** @var AttributeSetFactory */
    private $attributeSetFactory;

    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /**
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->moduleDataSetup = $moduleDataSetup;
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
        $this->addCustomerReference();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     * @throws Exception
     */
    public function addCustomerReference()
    {
        $customerEavSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

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

        /** @noinspection NullPointerExceptionInspection */
        $attribute->addData(
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => $this->customerReferenceForms,
            ]
        );

        /** @noinspection PhpDeprecationInspection */
        /** @noinspection NullPointerExceptionInspection */
        $attribute->save();
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '6.0.0';
    }
}
