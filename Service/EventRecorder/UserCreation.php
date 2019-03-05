<?php
namespace AristanderAi\Aai\Service\EventRecorder;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;

class UserCreation
{
    /** @var EventFactory */
    private $eventFactory;

    /** @var EventRepository */
    private $eventRepository;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param int $userId
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record($userId)
    {
        $event = $this->eventFactory->create(['type' => 'user_creation']);
        $event->collect();

        $event->setDetails([
            'user_id' => (string) $userId,
        ]);

        $this->eventRepository->save($event);

        return $this;
    }
}
