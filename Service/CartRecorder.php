<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Helper\Data;
use Magento\Quote\Model\Quote\Item;

class CartRecorder
{
    protected $events = [];
    
    /** @var EventFactory */
    protected $eventFactory;

    /** @var EventRepository */
    protected $eventRepository;

    /** @var Data */
    protected $helperData;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        Data $helperData
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->helperData = $helperData;
    }

    /**
     * Adds changed cart item for later use
     * Totals are recalculated later to we couldn't save events before cart save
     *
     * @param Item $item
     * @param string $action
     * @return self
     */
    public function addItemChange(Item $item, string $action): self
    {
        $this->events[] = compact('item', 'action');

        return $this;
    }

    /**
     * Generates and saves events for previously added changed cart items
     *
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @return self
     */
    public function saveEvents(): self
    {
        foreach ($this->events as $eventData) {
            /** @var Item $item */
            $item = $eventData['item'];

            /** @var string $action */
            $action = $eventData['action'];

            /** @var \AristanderAi\Aai\Model\Event $event */
            $event = $this->eventFactory->create(['type' => 'basket']);
            $event->collect();
            $event->setDetails([
                'action' => $action,
                'product_id' => (string) $item->getProduct()->getId(),
                'quantity' => 'deletion' != $action
                    ? (string) $item->getQty()
                    : '0',
                'price' => $this->helperData->formatPrice(
                    $item->getPrice()
                ),
                'tax_amount' => $this->helperData->formatPrice(
                    $item->getTaxAmount()
                ),
                'price_incl_tax' => $this->helperData->formatPrice(
                    $item->getPriceInclTax()
                ),
                'currency_code' => $item->getStore()->getCurrentCurrencyCode(),
            ]);

            $this->eventRepository->save($event);
        }

        return $this;
    }
}