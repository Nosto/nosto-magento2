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

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Nosto\Tagging\Api\Data\CustomerInterface;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Model\ResourceModel\Customer;
use Nosto\Tagging\Model\ResourceModel\Product\Queue as ProductQueue;

abstract class Core
{
    public function createCustomerTable(SchemaSetupInterface $setup)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $table = $setup->getConnection()
            ->newTable($setup->getTable(Customer::TABLE_NAME))
            ->addColumn(
                CustomerInterface::CUSTOMER_ID,
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary' => true,
                    'unsigned' => true
                ],
                'Customer ID'
            )
            ->addColumn(
                CustomerInterface::QUOTE_ID,
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                CustomerInterface::NOSTO_ID,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Nosto customer ID'
            )
            ->addColumn(
                CustomerInterface::CREATED_AT,
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Creation Time'
            )
            ->addColumn(
                CustomerInterface::UPDATED_AT,
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Updated Time'
            )
            ->addColumn(
                CustomerInterface::RESTORE_CART_HASH,
                Table::TYPE_TEXT,
                CustomerInterface::NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH,
                ['nullable' => true],
                'Restore cart hash'
            )
            ->addIndex(
                $setup->getIdxName(
                    Customer::TABLE_NAME,
                    [CustomerInterface::QUOTE_ID, CustomerInterface::NOSTO_ID]
                ),
                [CustomerInterface::QUOTE_ID, CustomerInterface::NOSTO_ID],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->setComment('Nosto customer and order mapping');
        /** @noinspection PhpUnhandledExceptionInspection */
        $setup->getConnection()->createTable($table);
    }

    public function createProductQueueTable(SchemaSetupInterface $setup)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $table = $setup->getConnection()
            ->newTable($setup->getTable(ProductQueue::TABLE_NAME))
            ->addColumn(
                ProductQueueInterface::ID,
                Table::TYPE_INTEGER,
                null,
                [
                    'auto_increment' => true,
                    'nullable' => false,
                    'identity' => true,
                    'primary' => true,
                    'unsigned' => true,
                ],
                'ID'
            )
            ->addColumn(
                ProductQueueInterface::PRODUCT_ID,
                Table::TYPE_INTEGER,
                null,
                [
                    'nullable' => false,
                    'unsigned' => true,
                ],
                'Product ID'
            )
            ->addColumn(
                ProductQueueInterface::CREATED_AT,
                Table::TYPE_DATETIME,
                null,
                ['nullable' => false],
                'Creation Time'
            )
            ->addIndex(
                $setup->getIdxName(
                    ProductQueue::TABLE_NAME,
                    [ProductQueueInterface::PRODUCT_ID]
                ),
                [ProductQueueInterface::PRODUCT_ID],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            );
        /** @noinspection PhpUnhandledExceptionInspection */
        $setup->getConnection()->createTable($table);
    }
}
