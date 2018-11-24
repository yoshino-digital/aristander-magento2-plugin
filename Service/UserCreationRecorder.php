<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;

class UserCreationRecorder
{
    /** @var EventFactory */
    protected $eventFactory;

    /** @var EventRepository */
    protected $eventRepository;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param int $userId
     * @return self
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record($userId)
    {
        $event = $this->eventFactory->create(['type' => 'user_creation']);
        $event->collect();

        $event->setDetails(array(
            'user_id' => $userId,
        ));

        $this->eventRepository->save($event);

        return $this;
    }
}