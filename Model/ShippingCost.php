<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\ShippingCostInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Class ShippingCost
 * @package AristanderAi\Aai\Model
 */

class ShippingCost extends AbstractModel implements ShippingCostInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel\ShippingCost::class);
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setQuoteId($value)
    {
        return $this->setData(self::QUOTE_ID, $value);
    }

    /**
     * @return string;
     */
    public function getCode()
    {
        return $this->getData(self::CODE);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCode($value)
    {
        return $this->setData(self::CODE, $value);
    }

    /**
     * @return float;
     */
    public function getCost()
    {
        return $this->getData(self::COST);
    }

    /**
     * @param float $value
     * @return $this
     */
    public function setCost($value)
    {
        return $this->setData(self::COST, $value);
    }
}
