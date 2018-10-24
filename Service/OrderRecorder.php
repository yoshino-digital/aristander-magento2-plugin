<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;

class OrderRecorder
{
    /** @var EventFactory */
    protected $eventFactory;

    /** @var EventRepository */
    protected $eventRepository;

    /** @var QuoteRepository */
    protected $quoteRepository;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        QuoteRepository $quoteRepository
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param Order $order
     * @return self
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record(Order $order): self
    {
        $event = $this->eventFactory->create(['type' => 'order']);
        $event->collectGeneralProperties();

        $details = [
            'order_id' => $order->getIncrementId(),
            'products' => [],
            'order_costs' => [],
        ];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getParentItem()) {
                // Filtering by parent_item_id in getAllVisibleItems does not work before save
                continue;
            }

            $origPrice = min($item->getPrice(), $item->getOriginalPrice());

            $details['products'][] = [
                'product_id' => $item->getProductId(),
                'quantity' => (float) $item->getQtyOrdered(),
                'discount' => $item->getDiscountAmount(),
                'additional_cost' => $item->getPriceInclTax() - $origPrice,
                'price' => $item->getPrice(),
                //TODO: remove if not used
                'item_price' => $item->getPrice(),
                'subtotal' => $item->getRowTotal(),
                'subtotal_modifier' => $item->getRowTotal()
                    - $origPrice * $item->getQtyOrdered(),
                'tax_amount' => $item->getTaxAmount(),
                'price_incl_tax' => $item->getPriceInclTax(),
                'currency_code' => $item->getStore()->getCurrentCurrencyCode(),
            ];
        }

        $quote = $this->quoteRepository->get($order->getQuoteId());

        /** @var Total $total */
        foreach ($quote->getTotals() as $total) {
            $code = $total->getCode();
            if ('subtotal' == $code || 'grand_total' == $code) {
                // Skip basic items
                continue;
            }

            $attribute = "{$code}_amount";
            $value = null;
            if ($order->hasData($attribute)) {
                // First try to get data from order as shipping cost is not updated in quote totals
                $value = $order->getData($attribute);
            } else if ($total->hasData('value')) {
                $value = $total->getData('value');
            } else {
                continue;
            }

            $details['order_costs'][] = [
                $code,
                $value,
            ];
        }

        // Discount is not reflected in quote totals so collect it explicitly
        $value = $order->getDiscountAmount();
        if (0 != $value) {
            $details['order_costs'][] = [
                'discount',
                $value,
            ];
        }

        $details['total'] = $order->getGrandTotal();
        $details['total_modifier']
            = $order->getGrandTotal() - $order->getSubtotal();

        $event->setDetails($details);

        $this->eventRepository->save($event);

        return $this;
    }
}