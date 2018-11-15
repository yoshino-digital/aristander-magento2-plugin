<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;

class LoginRecorder
{
    /** @var EventFactory */
    protected $eventFactory;

    /** @var EventRepository */
    protected $eventRepository;

    protected $events = [];

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @return self
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record(): self
    {
        $event = $this->eventFactory->create(['type' => 'login']);
        $event->collect();

        $event->setDetails([
            'user_id' => $event->getUserId(),
        ]);

        $this->eventRepository->save($event);

        return $this;
    }
}