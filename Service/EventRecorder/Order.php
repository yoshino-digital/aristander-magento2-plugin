<?php
namespace AristanderAi\Aai\Service\EventRecorder;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Helper\Data;
use Magento\Config\App\Config\Type\System as SystemConfig;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;

class Order
{
    /** @var array|null */
    private $configDataBackup;

    /** @var Store */
    private $store;

    /** @var EventFactory */
    private $eventFactory;

    /** @var EventRepository */
    private $eventRepository;

    /** @var QuoteRepository */
    private $quoteRepository;

    /** @var Data */
    private $helperData;

    /** @var SystemConfig */
    private $systemConfig;

    /** @var Address\RateRequestFactory */
    private $rateRequestFactory;

    /** @var Address\RateCollectorInterfaceFactory */
    private $rateCollector;

    public function __construct(
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        QuoteRepository $quoteRepository,
        Data $helperData,
        SystemConfig $systemConfig,
        Address\RateRequestFactory $rateRequestFactory,
        Address\RateCollectorInterfaceFactory $rateCollector
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->quoteRepository = $quoteRepository;
        $this->helperData = $helperData;
        $this->systemConfig = $systemConfig;
        $this->rateRequestFactory = $rateRequestFactory;
        $this->rateCollector = $rateCollector;
    }

    /**
     * @param OrderModel $order
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function record(OrderModel $order)
    {
        $event = $this->eventFactory->create(['type' => 'order']);
        $event->collect();

        $details = [
            'order_id' => (string) $order->getIncrementId(),
            'products' => [],
        ];

        //
        // Collect item data
        //

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

        //
        // Collect totals
        //

        $quote = $this->quoteRepository->get($order->getQuoteId());

        // Extract total taxes
        $totalTaxes = 0;
        /** @var Total $total */
        foreach ($quote->getTotals() as $total) {
            $code = $total->getCode();
            if ('tax' == $code) {
                $attribute = "{$code}_amount";
                if ($order->hasData($attribute)) {
                    // First try to get data from order as shipping cost is not updated in quote totals
                    $totalTaxes = $order->getData($attribute);
                } elseif ($total->hasData('value')) {
                    $totalTaxes = $total->getData('value');
                }

                break;
            }
        }

        $details['total'] = $this->helperData->formatPrice(
            $order->getGrandTotal()
        );
        $details['total_taxes'] = $this->helperData->formatPrice(
            $totalTaxes
        );

        //
        // Save event
        //

        $event->setDetails($details);

        $this->eventRepository->save($event);

        return $this;
    }
}
