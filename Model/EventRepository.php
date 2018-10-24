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
    protected $eventFactory;

    /** @var EventResource */
    protected $eventResource;

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
    public function get($id): EventInterface
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
    public function save(EventInterface $event): EventRepositoryInterface
    {
        /** @noinspection PhpParamsInspection */
        $this->eventResource->save($event);

        return $this;
    }
}