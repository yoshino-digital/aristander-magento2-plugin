<?php
namespace AristanderAi\Aai\Service\EventRecorder;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use Magento\Customer\Model\Customer;

class Login
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
     * @return $this
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
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
