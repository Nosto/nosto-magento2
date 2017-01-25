<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Nosto\Tagging\Api\Data\CustomerInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for Nosto Tagging module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) // @codingStandardsIgnoreLine
    {
        $installer = $setup;
        $installer->startSetup();
        $table = $installer->getConnection()
            ->newTable($installer->getTable('nosto_tagging_customer'))
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
            ->addIndex(
                $installer->getIdxName(
                    'nosto_tagging_customer',
                    ['quote_id', 'nosto_id']
                ),
                ['quote_id', 'nosto_id'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->setComment('Nosto customer and order mapping');

        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}
