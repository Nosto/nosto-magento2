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

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Nosto\Tagging\Api\Data\CustomerInterface;
use Nosto\Tagging\Model\Product\Cache;
use Nosto\Tagging\Model\ResourceModel\Customer;
use Nosto\Tagging\Model\ResourceModel\Product\Cache as CacheResource;
use Zend_Db_Exception;

class UpgradeSchema extends Core implements UpgradeSchemaInterface
{
    const PRODUCT_QUEUE_TABLE = 'nosto_tagging_product_queue';

    /**
     * {@inheritdoc}
     * @throws Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $fromVersion = $context->getVersion();
        if (version_compare($fromVersion, '2.1.0', '<')) {
            $this->addRestoreCartHash($setup);
        }
        if (version_compare($fromVersion, '4.0.3', '<=')) {
            $this->productCacheDataToLongtext($setup);
        }
        if (version_compare($fromVersion, '4.0.4', '<=')) {
            $this->createProductCacheTable($setup);
        }
        $setup->endSetup();
    }

    /**
     * Adds the restore cart hash to Nosto customer table
     *
     * @param SchemaSetupInterface $setup
     */
    private function addRestoreCartHash(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable(Customer::TABLE_NAME),
            CustomerInterface::RESTORE_CART_HASH,
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'Restore cart hash',
                'length' => CustomerInterface::NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH
            ]
        );
    }

    /**
     * Changes the product_data column to be longtext for Nosto product cache
     *
     * @param SchemaSetupInterface $setup
     */
    private function productCacheDataToLongtext(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->modifyColumn(
            $setup->getTable(CacheResource::TABLE_NAME),
            Cache::PRODUCT_DATA,
            [
                'type' => Table::TYPE_TEXT,
                'length' => self::PRODUCT_DATA_MAX_LENGTH,
                'nullable' => true,
                'comment' => 'Product data'
            ]
        );
    }
}
