<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use Magento\Customer\Model\Customer;

class LoginRecorder
{
    /** @var EventFactory */
    private $eventFactory;

    /** @var EventRepository */
    private $eventRepository;

    private $events = [];

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
    }

    /**
     * @param Customer $customer
     * @return self
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record(Customer $customer)
    {
        $event = $this->eventFactory->create(['type' => 'login']);
        $event->collect();

        $event->setDetails([
            'user_id' => (string) $customer->getId(),
        ]);

        $this->eventRepository->save($event);

        return $this;
    }
}
