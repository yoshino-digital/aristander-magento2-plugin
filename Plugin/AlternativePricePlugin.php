<?php
namespace AristanderAi\Aai\Plugin;

/**
 * Class AlternativePricePlugin
 * @package AristanderAi\Aai\Plugin
 *
 * Implements rewriting of price index values for Magento pre-2.3, where
 * price modifier mechanism was not implemented.
 * As prepare_catalog_product_price_index_table available in Magento 1.x
 * is not fired anymore, we have to plug into
 * \Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\DefaultPrice::reindexEntity
 * method and update price index table it writes to (catalog_product_index_price_tmp).
 * Around plugin is used because Magento was not sending arguments to after
 * methods at least as of 2.0.2.
 */

use AristanderAi\Aai\Helper\Price;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\DefaultPrice;
use Magento\Framework\App\ResourceConnection;

class AlternativePricePlugin
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

    public function aroundReindexEntity(
        DefaultPrice $subject,
        callable $proceed,
        $entityIds
    ) {
        $result = $proceed($entityIds);

        $updateFields = [
            'min_price',
            'max_price',
            'final_price',
            'price',
        ];

        $indexTable = $subject->getIdxTable();

        $bind = array();
        foreach ($updateFields as $priceField) {
            $bind[$priceField] = new \Zend_Db_Expr('tier_price');
        }

        $where = [
            'customer_group_id = ?' => $this->helperPrice->getCustomerGroupId(),
            'NOT ISNULL(tier_price)',
        ];
        if ($entityIds) {
            $where['entity_id IN (?)'] = (array)$entityIds;
        }

        $this->resource->getConnection($this->connectionName)->update(
            $indexTable,
            $bind,
            $where
        );

        return $result;
    }
}
