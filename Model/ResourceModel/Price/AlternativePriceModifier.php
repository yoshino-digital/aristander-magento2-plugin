<?php
namespace AristanderAi\Aai\Model\ResourceModel\Price;

use AristanderAi\Aai\Helper\Price;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\PriceModifierInterface;
use Magento\Framework\App\ResourceConnection;

class AlternativePriceModifier implements PriceModifierInterface
{
    /** @var Price */
    private $helperPrice;

    /** @var ResourceConnection */
    private $resource;

    /** @var string */
    private $connectionName;

    public function __construct(
        Price $helperPrice,
        ResourceConnection $resource,
        $connectionName = 'indexer'
    ) {
        $this->helperPrice = $helperPrice;
        $this->resource = $resource;
        $this->connectionName = $connectionName;
    }

    /** @noinspection PhpLanguageLevelInspection */
    /**
     * Modify price data.
     *
     * @param IndexTableStructure $priceTable
     * @param array $entityIds
     * @return void
     */
    public function modifyPrice(IndexTableStructure $priceTable, array $entityIds = []) : void
    {
        $updateFields = [
            $priceTable->getMinPriceField(),
            $priceTable->getMaxPriceField(),
            $priceTable->getFinalPriceField(),
            $priceTable->getOriginalPriceField(),
        ];

        $indexTable = $priceTable->getTableName();

        $bind = array();
        foreach ($updateFields as $priceField) {
            $bind[$priceField] = new \Zend_Db_Expr('tier_price');
        }

        $where = [
            "{$priceTable->getCustomerGroupField()} = ?" => $this->helperPrice->getCustomerGroupId(),
            "NOT ISNULL({$priceTable->getTierPriceField()})",
        ];
        if ($entityIds) {
            $where["{$priceTable->getEntityField()} IN (?)"] = $entityIds;
        }

        $this->resource->getConnection($this->connectionName)->update(
            $indexTable,
            $bind,
            $where
        );
    }
}