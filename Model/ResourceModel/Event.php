<?php
namespace AristanderAi\Aai\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Event extends AbstractDb
{
    /** @var DateTime */
    private $date;

    public function __construct(
        Context $context,
        DateTime $date,
        $connectionName = null
    ) {
        $this->date = $date;

        parent::__construct($context, $connectionName);
    }

    protected function _construct()
    {
        $this->_init('aai_event', 'id');
        $this->_serializableFields = ['details' => [[], []]];
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException if not properly configured
     * @return $this
     */
    public function cleanUp()
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            [
                'synced_at < ?' => $this->date->gmtDate(
                    null,
                    strtotime('-1 day')
                ),
            ]
        );

        return $this;
    }
}
