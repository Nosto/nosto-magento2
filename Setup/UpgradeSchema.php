<?php

namespace Nosto\Tagging\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Nosto\Tagging\Api\Data\CustomerInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '2.1.0', '<')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('nosto_tagging_customer'),
                CustomerInterface::RESTORE_CART_HASH,
                [
                    'type' => Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Restore cart hash',
                    'length' => CustomerInterface::NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH
                ]
            );
        }

        $setup->endSetup();
    }
}