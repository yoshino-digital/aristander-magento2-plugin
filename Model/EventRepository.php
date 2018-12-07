<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\EventInterface;
use AristanderAi\Aai\Api\EventRepositoryInterface;
use AristanderAi\Aai\Model\ResourceModel\Event as EventResource;

/**
 * Class EventRepository
 * @package AristanderAi\Aai\Model
 */

class EventRepository implements EventRepositoryInterface
{
    /**
     * @var EventFactory
     */
    private $eventFactory;

    /** @var EventResource */
    private $eventResource;

    public function __construct(
        EventFactory $eventFactory,
        EventResource $eventResource

    ) {
        $this->eventFactory = $eventFactory;
        $this->eventResource = $eventResource;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $result = $this->eventFactory->create();
        $this->eventResource->load($result, $id);

        return $result;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(EventInterface $event)
    {
        /** @noinspection PhpParamsInspection */
        $this->eventResource->save($event);

        return $this;
    }
}
