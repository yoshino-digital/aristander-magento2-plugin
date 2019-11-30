<?php
namespace AristanderAi\Aai\Setup;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\Price;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\State;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Indexer\Model\IndexerFactory;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /** @var EavSetupFactory */
    private $eavSetupFactory;

    /** @var Data */
    private $helperData;

    /** @var Price */
    private $helperPrice;

    /** @var State */
    private $appState;

    /** @var IndexerFactory */
    private $indexerFactory;

    /** @var Manager */
    private $cacheManager;

    /**
     * UpgradeData constructor.
     * @param EavSetupFactory $eavSetupFactory
     * @param Data $helperData
     * @param Price $helperPrice
     * @param State $appState
     * @param IndexerFactory $indexerFactory
     * @param Manager $cacheManager
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Data $helperData,
        Price $helperPrice,
        State $appState,
        IndexerFactory $indexerFactory,
        Manager $cacheManager
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->helperData = $helperData;
        $this->helperPrice = $helperPrice;
        $this->appState = $appState;
        $this->indexerFactory = $indexerFactory;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     * @throws \Throwable
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        if ('' == $context->getVersion()) {
            $this->install($setup, $context);
            return;
        }

        $db = $setup->getConnection();

        if (version_compare($context->getVersion(), '1.2', '<')) {
            // Upgrade to v1.2
            $setup->startSetup();

            $this->addAlternativePriceCustomerGroup();
            $this->addAlternativePriceProductAttribute();

            /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
            $eavSetup = $this->eavSetupFactory->create();
            $eavSetup->removeAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'aai_backup_price'
            );

            // Reindex prices
            $this->appState->setAreaCode( //Fixes the area code not set error
                \Magento\Framework\App\Area::AREA_ADMINHTML
            );

            /** @var \Magento\Indexer\Model\Indexer $process */
            $process = $this->indexerFactory->create();
            $process->load('catalog_product_price');
            $process->reindexAll();

            $setup->endSetup();
        }

        if (version_compare($context->getVersion(), '1.3', '<')) {
            // Upgrade to v1.3
            $setup->startSetup();

            // Update price_mode
            $configPath = 'aai/price_import/price_mode';
            $priceModeRename = [
                'original' => 'fixed_original',
                'alternative' => 'fixed_aristander',
            ];

            $table = $setup->getTable('core_config_data');

            foreach ($priceModeRename as $form => $to) {
                $db->update(
                    $table,
                    array('value' => $to),
                    array(
                        'path = ?' => $configPath,
                        'value = ?' => $form,
                    )
                );
            }

            // Rename option
            $db->update(
                $table,
                array('path' => 'aai/price/mode'),
                array(
                    'path = ?' => $configPath,
                )
            );
            // Delete option
            $db->delete(
                $table,
                array(
                    'path = ?' => 'aai/price_import/enabled',
                )
            );

            // Clean config cache
            $this->cacheManager->clean([
                \Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER
            ]);

            $setup->endSetup();
        }

        if (version_compare($context->getVersion(), '1.3.1', '<')) {
            // Upgrade to v1.3.1
            $setup->startSetup();

            $table = $setup->getTable('ui_bookmark');

            // Delete bookmark to reset column order
            $db->delete(
                $table,
                array(
                    'namespace = ?' => 'product_listing',
                )
            );

            $setup->endSetup();
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        $this->addAlternativePriceCustomerGroup();
        $this->addAlternativePriceProductAttribute();

        $setup->endSetup();
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function addAlternativePriceCustomerGroup()
    {
        $this->helperPrice->initCustomerGroup();

        return $this;
    }

    /**
     * @return $this
     */
    public function addAlternativePriceProductAttribute()
    {
        /** @var \Magento\Eav\Setup\EavSetup $setup */
        $setup = $this->eavSetupFactory->create();

        $code = $this->helperPrice->getAlternativePriceAttributeCode();

        $setup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $code,
            [
                'type' => 'decimal',
                'label' => 'Aristander Price',
                'group' => 'Prices',
                'input' => 'price',
                'required' => false,
                'user_defined' => false,
            ]
        );

        // Hide from admin
        $setup->updateAttribute(
            'catalog_product',
            $code,
            'is_visible',
            '0'
        );

        // Use on front
        $setup->updateAttribute(
            'catalog_product',
            $code,
            'used_in_product_listing',
            '1'
        );

        return $this;
    }
}
