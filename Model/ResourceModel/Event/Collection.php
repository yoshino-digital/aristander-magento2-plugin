<?php
namespace AristanderAi\Aai\Model\ResourceModel\Event;

use AristanderAi\Aai\Model\Event;
use AristanderAi\Aai\Model\ResourceModel\Event as EventResource;
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
            Event::class,
            EventResource::class
        );
    }

    /**
     * Adds status filter
     *
     * @param string|array $status
     * @return self
     */
    public function setStatusFilter($status)
    {
        if (is_array($status)) {
            $this->getSelect()->where('status IN (?)', $status);
        } else {
            $this->getSelect()->where('status = ?', $status);
        }

        return $this;
    }

    /**
     * Unserialize details field
     *
     * @return self
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        /** @var Event $item */
        foreach ($this->getItems() as $item) {
            $this->getResource()->unserializeFields($item);
            $item->setDataChanges(false);
        }
        return $this;
    }
}
