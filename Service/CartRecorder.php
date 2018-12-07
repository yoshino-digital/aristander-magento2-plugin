<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Model\ResourceModel\Event\Collection;
use AristanderAi\Aai\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use AristanderAi\Aai\Helper\Data;
use Magento\Quote\Model\Quote\Item;

class CartRecorder
{
    private $events = [];
    
    /** @var EventFactory */
    private $eventFactory;

    /** @var EventCollectionFactory */
    private $eventCollectionFactory;

    /** @var EventRepository */
    private $eventRepository;

    /** @var Data */
    private $helperData;

    public function __construct(
        EventFactory $eventFactory,
        EventCollectionFactory $eventCollectionFactory,
        EventRepository $eventRepository,
        Data $helperData
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventCollectionFactory = $eventCollectionFactory;
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
    public function addItemChange(Item $item, $action)
    {
        $this->events[] = [
            'item' => $item,
            'action' => $action,
        ];

        return $this;
    }

    /**
     * Generates and saves events for previously added changed cart items
     *
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @return self
     */
    public function saveEvents()
    {
        /** @var Collection $eventCollection */
        $eventCollection = $this->eventCollectionFactory->create();

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

            $eventCollection->addItem($event);
        }

        // Save all events
        $eventCollection->walk([$this->eventRepository, 'save']);

        return $this;
    }
}
