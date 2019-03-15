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
    /** @var \ReflectionProperty|null */
    private $configDataReflection;

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
            'order_costs' => [],
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

        //
        // Collect shipping revenue and profit
        //

        $shippingMethod = $order->getShippingMethod(true);
        $shippingCarrierCode = $shippingMethod->getData('carrier_code');
        $shippingMethodCode = $shippingMethod->getData('method');
        /** @var Address $address */
        $address = $quote->getShippingAddress();

        $this->beforeRequestShippingRates($quote);

        $this->setTmpCarrierConfig(
            $shippingCarrierCode,
            'free_shipping_enable',
            false
        );
        $this->setTmpCarrierConfig(
            $shippingCarrierCode,
            'handling_fee',
            0
        );

        $shippingCost = $this->requestShippingRate(
            $address,
            $shippingCarrierCode,
            $shippingMethodCode
        );

        $this->afterRequestShippingRates($quote);

        $shippingRevenue = $order->getShippingAmount();

        $details['shipping'] = array(
            'revenue' => $shippingRevenue,
            'revenue_incl_tax' => $order->getShippingInclTax(),
            'profit' => $shippingRevenue - $shippingCost,
            'currency_code' => $order->getStore()->getCurrentCurrencyCode(),
        );

        //
        // Save event
        //

        $event->setDetails($details);

        $this->eventRepository->save($event);

        return $this;
    }

    /**
     * Requests shipping rate
     *
     * @param Address $address
     * @param $carrier
     * @param $method
     * @return float|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function requestShippingRate(
        Address $address,
        $carrier,
        $method
    ) {
        /**
         * Based on core code
         * @see \Magento\Quote\Model\Quote\Address::requestShippingRates
         */

        /** @var $request Address\RateRequest */
        $request = $this->rateRequestFactory->create();
        $request->setAllItems($address->getAllItems());
        $request->setDestCountryId($address->getCountryId());
        $request->setDestRegionId($address->getRegionId());
        $request->setDestRegionCode($address->getRegionCode());
        $request->setDestStreet($address->getStreetFull());
        $request->setDestCity($address->getCity());
        $request->setDestPostcode($address->getPostcode());
        $request->setPackageValue($address->getBaseSubtotal());
        $request->setPackageValueWithDiscount(
            $address->getBaseSubtotalWithDiscount()
        );
        $request->setPackageWeight($address->getWeight());
        $request->setPackageQty($address->getItemQty());

        /**
         * Need for shipping methods that use insurance based on price of physical products
         */
        /** @noinspection PhpUndefinedMethodInspection */
        $request->setPackagePhysicalValue(
            $address->getBaseSubtotal() - $address->getBaseVirtualAmount()
        );

        /** @noinspection PhpUndefinedMethodInspection */
        $request->setFreeMethodWeight($address->getFreeMethodWeight());

        /**
         * Store and website identifiers specified from StoreManager
         */
        $request->setStoreId($this->store->getId());
        $request->setWebsiteId($this->store->getWebsite()->getId());
        $request->setFreeShipping(0);
        /**
         * Currencies need to convert in free shipping
         */
        /** @noinspection PhpUndefinedMethodInspection */
        $request->setBaseCurrency($this->store->getBaseCurrency());
        /** @noinspection PhpUndefinedMethodInspection */
        $request->setPackageCurrency($this->store->getCurrentCurrency());
        $request->setLimitCarrier($carrier);
        /** @noinspection PhpUndefinedMethodInspection */
        $request->setBaseSubtotalInclTax($address->getBaseSubtotalTotalInclTax());

        /** @noinspection PhpUndefinedMethodInspection */
        $result = $this->rateCollector->create()->collectRates($request)
            ->getResult();

        if (!$result) {
            // Request failed
            return null;
        }

        /** @var Method $rate */
        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($result->getAllRates() as $rate) {
            if ($method == $rate->getData('method')) {
                return $rate->getData('price');
            }
        }

        // Method not found
        return null;
    }

    /**
     * Prepares to original shipping rate request
     *
     * @param Quote $quote
     * @throws \ReflectionException
     */
    private function beforeRequestShippingRates(Quote $quote)
    {
        $this->store = $quote->getStore();

        $reflectionClass = new \ReflectionClass($this->systemConfig);
        $this->configDataReflection = $reflectionClass->getProperty(
            'data'
        );
        $this->configDataReflection->setAccessible(true);
        $this->configDataBackup = $this->configDataReflection->getValue(
            $this->systemConfig
        );
        if (null === $this->configDataBackup) {
            $this->configDataBackup = [];
        }

        // Reset free_shipping flag
        $address = $quote->getShippingAddress();
        $this->resetFreeShipping($address);
        foreach ($address->getAllItems() as $item) {
            $this->resetFreeShipping($item);
        }
    }

    /**
     * Restores config after original shipping rate request
     */
    private function afterRequestShippingRates(Quote $quote)
    {
        if (null !== $this->configDataBackup) {
            $this->configDataReflection->setValue(
                $this->systemConfig,
                $this->configDataBackup
            );
        }

        $this->configDataReflection->setAccessible(false);
        $this->configDataReflection = null;
        $this->configDataBackup = null;
        $this->store = null;

        // Restore free_shipping flag
        $address = $quote->getShippingAddress();
        $this->restoreFreeShipping($address);
        foreach ($address->getAllItems() as $item) {
            $this->restoreFreeShipping($item);
        }
    }

    /**
     * Sets temporary shipping carries config value
     *
     * @param string $carrierCode
     * @param string $field
     * @param string $value
     */
    private function setTmpCarrierConfig($carrierCode, $field, $value)
    {
        $this->setTmpStoreConfig(
            "carriers/{$carrierCode}/{$field}",
            $value
        );
    }

    /**
     * Sets temporary store config value via cache config hack
     *
     * @param string $path
     * @param string $value
     */
    private function setTmpStoreConfig($path, $value)
    {
        $path = "stores/{$this->store->getCode()}/$path";
        $pathParts = explode('/', $path);
        $lastKey = array_pop($pathParts);

        $data = $this->configDataReflection->getValue($this->systemConfig);
        if (!is_array($data)) {
            $data = [];
        }

        $dataRef = & $data;
        foreach ($pathParts as $key) {
            if (!is_array($dataRef[$key])) {
                $dataRef[$key] = [];
            }

            $dataRef = & $dataRef[$key];
        }

        $dataRef[$lastKey] = $value;

        $this->configDataReflection->setValue($this->systemConfig, $data);
    }

    private function resetFreeShipping(DataObject $object)
    {
        if ($object->hasData('free_shipping')) {
            $object->setData(
                'free_shipping_bak',
                $object->getData('free_shipping')
            );
            $object->setData('free_shipping', 0);
        }
    }

    private function restoreFreeShipping(DataObject $object)
    {
        if ($object->hasData('free_shipping_bak')) {
            $object->setData(
                'free_shipping',
                $object->getData('free_shipping_bak')
            );
            $object->unsetData('free_shipping_bak');
        }
    }
}
