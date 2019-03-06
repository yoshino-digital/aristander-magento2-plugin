<?php
namespace AristanderAi\Aai\Model\ResourceModel\ShippingCost;

use AristanderAi\Aai\Model\ShippingCost;
use AristanderAi\Aai\Model\ResourceModel\ShippingCost as Resource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Resource collection initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            ShippingCost::class,
            Resource::class
        );
    }
}
