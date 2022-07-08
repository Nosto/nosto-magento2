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

namespace Nosto\Tagging\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table as MagentoTable;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Nosto\Tagging\Model\ResourceModel\Customer as NostoCustomer;
use Nosto\Tagging\Api\Data\CustomerInterface;
use Nosto\Tagging\Logger\Logger;
use Zend_Db_Exception;

class MakeCustomerIdNullable implements SchemaPatchInterface
{
    /** @var SchemaSetupInterface */
    private SchemaSetupInterface $schemaSetup;

    /** @var Logger */
    private Logger $logger;
    /**
     * MakeCustomerIdNullable constructor.
     * @param SchemaSetupInterface $schemaSetup
     * @param Logger $logger
     */
    public function __construct(
        SchemaSetupInterface $schemaSetup,
        Logger $logger
    ) {
        $this->schemaSetup = $schemaSetup;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->schemaSetup->startSetup();
        $connection = $this->schemaSetup->getConnection();

        $connection->dropTable(NostoCustomer::TABLE_NAME);

        $nostoCustomerTable = $connection->newTable(NostoCustomer::TABLE_NAME);
        try {
            $nostoCustomerTable->addColumn(
                'id',
                MagentoTable::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                    'identity' => true
                ],
                'ID'
            );
            $nostoCustomerTable->addColumn(
                CustomerInterface::CUSTOMER_ID,
                MagentoTable::TYPE_INTEGER,
                null,
                [
                    'type' => MagentoTable::TYPE_INTEGER,
                    'nullable' => true,
                    'unsigned' => true
                ],
                'Magento Customer ID'
            );
            $nostoCustomerTable->addColumn(
                'quote_id',
                MagentoTable::TYPE_INTEGER,
                null,
                [
                    'nullable' => true,
                    'unsigned' => true,
                ]
            );
            $nostoCustomerTable->addColumn(
                'nosto_id',
                MagentoTable::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Nosto Customer ID'
            );
            $nostoCustomerTable->addColumn(
                'created_at',
                MagentoTable::TYPE_DATETIME,
                null,
                ['nullable' => true],
                'Creation Time'
            );
            $nostoCustomerTable->addColumn(
                'updated_at',
                MagentoTable::TYPE_DATETIME,
                null,
                ['nullable' => true],
                'Updated Time'
            );
            $nostoCustomerTable->addColumn(
                'restore_cart_hash',
                MagentoTable::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Nosto Customer ID'
            );
            $nostoCustomerTable->addIndex(
                $this->schemaSetup->getIdxName(NostoCustomer::TABLE_NAME, ['quote_id']),
                ['quote_id']
            );
            $connection->createTable($nostoCustomerTable);
        } catch (Zend_Db_Exception $e) {
            $this->logger->error(
                sprintf(
                    'Could not create %s table. Error was: %s',
                    NostoCustomer::TABLE_NAME,
                    $e->getMessage()
                )
            );
        }
        $this->schemaSetup->endSetup();
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
    public function getAliases(): array
    {
        return [];
    }
}
