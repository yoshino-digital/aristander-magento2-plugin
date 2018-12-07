<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Helper\Data;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;

class OrderRecorder
{
    /** @var EventFactory */
    private $eventFactory;

    /** @var EventRepository */
    private $eventRepository;

    /** @var QuoteRepository */
    private $quoteRepository;

    /** @var Data */
    private $helperData;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        QuoteRepository $quoteRepository,
        Data $helperData
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->quoteRepository = $quoteRepository;
        $this->helperData = $helperData;
    }

    /**
     * @param Order $order
     * @return self
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record(Order $order)
    {
        $event = $this->eventFactory->create(['type' => 'order']);
        $event->collect();

        $details = [
            'order_id' => (string) $order->getIncrementId(),
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
                'product_id' => (string) $item->getProductId(),
                'quantity' => (string) $item->getQtyOrdered(),
                'discount' => $this->helperData->formatPrice(
                    $item->getDiscountAmount()
                ),
                'additional_cost' => $this->helperData->formatPrice(
                    $item->getPriceInclTax() - $origPrice
                ),
                'price' => $this->helperData->formatPrice(
                    $item->getPrice()
                ),
                'item_price' => $this->helperData->formatPrice(
                    $item->getPrice()
                ),
                'subtotal' => $this->helperData->formatPrice(
                    $item->getRowTotal()
                ),
                'subtotal_modifier' => $this->helperData->formatPrice(
                    $item->getRowTotal() - $origPrice * $item->getQtyOrdered()
                ),
                'tax_amount' => $this->helperData->formatPrice(
                    $item->getTaxAmount()
                ),
                'price_incl_tax' => $this->helperData->formatPrice(
                    $item->getPriceInclTax()
                ),
                'currency_code' => $item->getStore()->getCurrentCurrencyCode(),
            ];
        }

        $quote = $this->quoteRepository->get($order->getQuoteId());

        $orderCosts = [];
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
            } elseif ($total->hasData('value')) {
                $value = $total->getData('value');
            } else {
                continue;
            }

            $orderCosts[$code] = $value;
        }

        if (!isset($orderCosts['discount'])) {
            // Discount is not reflected in quote totals so collect it explicitly
            $value = $order->getDiscountAmount();
            if (0 != $value) {
                $orderCosts['discount'] = $value;
            }
        }
        
        foreach ($orderCosts as $code => $value) {
            $details['order_costs'][] = [
                $code,
                $this->helperData->formatPrice($value)
            ];
        }

        $details['total'] = $this->helperData->formatPrice(
            $order->getGrandTotal()
        );
        $details['total_modifier'] = $this->helperData->formatPrice(
            $order->getGrandTotal() - $order->getSubtotal()
        );

        $event->setDetails($details);

        $this->eventRepository->save($event);

        return $this;
    }
}
