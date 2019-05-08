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

namespace Nosto\Tagging\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Magento\Framework\Exception\LocalizedException;

class UpgradeData implements UpgradeDataInterface
{
    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var WriterInterface */
    private $config;

    /** @var CustomerSetupFactory */
    private $customerSetupFactory;

    /** @var AttributeSetFactory */
    private $attributeSetFactory;

    /**
     * UpgradeData constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param WriterInterface $appConfig
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        WriterInterface $appConfig,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->config = $appConfig;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) // @codingStandardsIgnoreLine
    {
        if (version_compare($context->getVersion(), '3.1.0', '>=')) {
            $this->insertStoreDomain();
        }

        if (version_compare($context->getVersion(), '3.6.0', '>=')) {
            $this->addCustomerReference($setup);
        }
    }

    /**
     * Insert store domain when missing in database
     */
    private function insertStoreDomain()
    {
        $stores = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($stores as $store) {
            $storeFrontDomain = $this->nostoHelperAccount->getStoreFrontDomain($store);
            if ($storeFrontDomain === null ||
                $storeFrontDomain === ''
            ) {
                // @codingStandardsIgnoreLine
                $this->config->save(
                    NostoHelperAccount::XML_PATH_DOMAIN,
                    $this->nostoHelperUrl->getActiveDomain($store),
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }
        }
    }

    /**
     * Create a new field for Customer Reference
     *
     * @param ModuleDataSetupInterface $setup
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     */
    private function addCustomerReference(ModuleDataSetupInterface $setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(
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
                'unique' => false,
                'system' => false,
            ]
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(
            Customer::ENTITY,
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
        );

        $attribute->addData(
            [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer', 'customer_account_edit'],
            ]
        );

        $attribute->save();
    }
}
