<?php
namespace AristanderAi\Aai\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        //handle all possible upgrade versions

        if ('' != $context->getVersion()) {
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
        }

        $setup->endSetup();
    }
}
