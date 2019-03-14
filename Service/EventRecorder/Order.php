<?php
namespace AristanderAi\Aai\Service\EventRecorder;

use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Helper\Data;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order as OrderModel;

class Order
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
    )
    {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->quoteRepository = $quoteRepository;
        $this->helperData = $helperData;
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
            'order_id' => (string)$order->getIncrementId(),
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
                'product_id' => (string)$item->getProductId(),
                'quantity' => (string)$item->getQtyOrdered(),
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

        //TODO: collect shipping info

        $event->setDetails($details);

        $this->eventRepository->save($event);

        return $this;
    }

    /**
     * Requests shipping rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param $carrier
     * @param $method
     * @return float|null
     */
    private function requestShippingRate(
        Mage_Sales_Model_Quote_Address $address,
        $carrier,
        $method
    )
    {
        /**
         * Based on core code
         * @see \Mage_Sales_Model_Quote_Address::requestShippingRates
         */

        /** @var $request Mage_Shipping_Model_Rate_Request */
        $request = Mage::getModel('shipping/rate_request');
        $request->setAllItems($address->getAllItems());
        $request->setDestCountryId($address->getCountryId());
        $request->setDestRegionId($address->getRegionId());
        $request->setDestRegionCode($address->getRegionCode());
        /**
         * need to call getStreet with -1
         * to get data in string instead of array
         */
        $request->setDestStreet(
            $address->getStreet(
                Mage_Sales_Model_Quote_Address::DEFAULT_DEST_STREET
            )
        );
        $request->setDestCity($address->getCity());
        $request->setDestPostcode($address->getPostcode());
        $request->setPackageValue($address->getBaseSubtotal());
        $packageValueWithDiscount = $address->getBaseSubtotalWithDiscount();
        $request->setPackageValueWithDiscount($packageValueWithDiscount);
        $request->setPackageWeight($address->getWeight());
        $request->setPackageQty($address->getItemQty());

        /**
         * Need for shipping methods that use insurance based on price of physical products
         */
        /** @noinspection PhpUndefinedMethodInspection */
        $packagePhysicalValue = $address->getBaseSubtotal() - $address->getBaseVirtualAmount();
        $request->setPackagePhysicalValue($packagePhysicalValue);

        /** @noinspection PhpUndefinedMethodInspection */
        $request->setFreeMethodWeight($address->getFreeMethodWeight());

        /**
         * Store and website identifiers need specify from quote
         */
        /*$request->setStoreId(Mage::app()->getStore()->getId());
        $request->setWebsiteId(Mage::app()->getStore()->getWebsiteId());*/

        $request->setStoreId($this->store->getId());
        $request->setWebsiteId($this->store->getWebsiteId());
        $request->setFreeShipping($address->getFreeShipping());
        /**
         * Currencies need to convert in free shipping
         */
        $request->setBaseCurrency($this->store->getBaseCurrency());
        $request->setPackageCurrency($this->store->getCurrentCurrency());
        $request->setLimitCarrier($carrier);

        /** @noinspection PhpUndefinedMethodInspection */
        $request->setBaseSubtotalInclTax(
            $address->getBaseSubtotalInclTax() + $address->getBaseExtraTaxAmount()
        );

        /** @noinspection PhpUndefinedMethodInspection */
        $rateResult = Mage::getModel('shipping/shipping')
            ->collectRates($request)
            ->getResult();

        if (!$rateResult) {
            // Request failed
            return null;
        }

        /** @var Mage_Shipping_Model_Rate_Result_Method $rate */
        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($rateResult->getAllRates() as $rate) {
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
     * @param Mage_Sales_Model_Quote $quote
     * @throws ReflectionException
     */
    private function beforeRequestShippingRates(Mage_Sales_Model_Quote $quote)
    {
        $this->store = $quote->getStore();

        $reflectionClass = new ReflectionClass($this->store);
        $this->configCacheReflection = $reflectionClass->getProperty(
            '_configCache'
        );
        $this->configCacheReflection->setAccessible(true);
        $this->configCacheBackup = $this->configCacheReflection->getValue(
            $this->store
        );
        if (null === $this->configCacheBackup) {
            $this->configCacheBackup = array();
        }
    }

    /**
     * Restores config after original shipping rate request
     */
    private function afterRequestShippingRates()
    {
        if (null !== $this->configCacheBackup) {
            $this->configCacheReflection->setValue(
                $this->store,
                $this->configCacheBackup
            );
        }

        $this->configCacheReflection->setAccessible(false);
        $this->configCacheReflection = null;
        $this->configCacheBackup = null;
        $this->store = null;
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
        $cache = $this->configCacheReflection->getValue($this->store);
        $cache[$path] = $value;

    }
}
