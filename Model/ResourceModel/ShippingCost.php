<?php
namespace AristanderAi\Aai\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShippingCost extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('aai_shipping_cost', 'id');
    }

    /**
     * Get record identifier by quote ID and shipping method code
     *
     * @param int $quoteId
     * @param string $code
     * @return int|false
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getIdByNaturalKey($quoteId, $code)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from($this->getMainTable(), 'id')
            ->where('quoteId = :quoteId')
            ->where('code = :code');

        $bind = [
            ':quoteId' => $quoteId,
            ':code' => $code,
        ];

        return $connection->fetchOne($select, $bind);
    }

    /**
     * Deletes all records for specified quote ID
     *
     * @param $quoteId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteByQuote($quoteId)
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            ['quote_id = ?' => $quoteId]
        );
    }
}
