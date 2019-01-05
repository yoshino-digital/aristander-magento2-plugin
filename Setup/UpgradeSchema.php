<?php
namespace AristanderAi\Aai\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     * @throws \Zend_Db_Exception
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        if ('' == $context->getVersion()) {
            $this->install($setup, $context);
            return;
        }

        $setup->startSetup();

        //handle all possible upgrade versions
        $db = $setup->getConnection();

        if (version_compare($context->getVersion(), '0.1', '<')) {
            // Upgrade to v0.1

            $table = $setup->getTable('aai_event');

            // Add version field
            $db->addColumn(
                $table,
                'version',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => false,
                    'comment' => 'Version of the module at the time of event registration'
                ]
            );

            // Add timestamp field
            $db->addColumn(
                $table,
                'timestamp',
                [
                    'type' => Table::TYPE_INTEGER,
                    'nullable' => false,
                    'comment' => 'Event registration UNIX timestamp'
                ]
            );
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Zend_Db_Exception
     */
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        $tableName = 'aai_event';
        if (!$setup->tableExists($tableName)) {
            $table = $setup->getConnection()->newTable(
                $setup->getTable($tableName)
            )
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary'  => true,
                        'unsigned' => true,
                    ],
                    'Event ID'
                )
                ->addColumn(
                    'type',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Event Type'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false, 'default' => 'pending'],
                    'Event Sync Status'
                )
                ->addColumn(
                    'session_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Visitor Session Key'
                )
                ->addColumn(
                    'user_agent',
                    Table::TYPE_TEXT,
                    '64K',
                    ['nullable' => false],
                    'Visitor User Agent String'
                )
                ->addColumn(
                    'user_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Customer ID'
                )
                ->addColumn(
                    'store_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Store View ID'
                )
                ->addColumn(
                    'store_group_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Store ID'
                )
                ->addColumn(
                    'website_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Website ID'
                )
                ->addColumn(
                    'details',
                    Table::TYPE_TEXT,
                    '1M',
                    ['nullable' => true],
                    'Event Details'
                )
                ->addColumn(
                    'version',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'Version of the module at the time of event registration'
                )
                ->addColumn(
                    'timestamp',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false],
                    'vent registration UNIX timestamp'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Created At'
                )
                ->addColumn(
                    'synced_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => true],
                    'Synchronization Date'
                )
                ->addColumn(
                    'last_error',
                    Table::TYPE_TEXT,
                    '64K',
                    ['nullable' => true],
                    'Last Sync Error Message'
                )
                ->setComment('Event Log and Sync Queue');
            $setup->getConnection()->createTable($table);

            $idxFields = 'status';
            $setup->getConnection()->addIndex(
                $setup->getTable($tableName),
                $setup->getIdxName(
                    $setup->getTable($tableName),
                    $idxFields,
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                $idxFields,
                AdapterInterface::INDEX_TYPE_INDEX
            );
        }

        $setup->endSetup();
    }
}
