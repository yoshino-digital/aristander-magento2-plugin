<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Model\EventFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;

class PageRecorder
{
    /** @var \AristanderAi\Aai\Model\Event */
    protected $event;

    /** @var EventFactory */
    protected $eventFactory;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var Session */
    protected $session;

    public function __construct(
        EventFactory $eventFactory,
        StoreManagerInterface $storeManager,
        Session $session
    ) {
        $this->eventFactory = $eventFactory;
        $this->storeManager = $storeManager;
        $this->session = $session;
    }

    /**
     * Starts tracking page view event
     *
     * @return self
     */
    public function start(): self
    {
        $this->event = $this->eventFactory->create(['type' => 'page']);

        // Init details array
        $this->event->setDetails([
            'products' => [],
        ]);

        return $this;
    }

    /**
     * Finalizes page record data collection and returns event object
     *
     * @return \AristanderAi\Aai\Model\Event|null
     */
    public function exportEvent()
    {
        if (!$this->isStarted()) {
            return null;
        }

        // Workaround for cart and checkout pages not registering products
        $details = $this->event->getDetails();
        if (empty($details['products'])
            && isset($details['page_name'])
            && in_array($details['page_name'], ['basket', 'checkout'])
        ) {
            // Collect cart items from quote
            /** @var Item $item */
            foreach ($this->session->getQuote()->getAllVisibleItems() as $item) {
                $this->recordProduct($item->getProduct());
            }
        }

        $result = clone $this->event;

        $details = $result->getDetails();

        // Push main product to the beginning
        $products = $details['products'];
        if (isset($details['product_id'])) {
            $productId = $details['product_id'];
            assert(isset($products[$productId]), 'Main product not recorded');
            if (isset($products[$productId])) {
                // Put main product to the beginning
                $productItem = $products[$productId];
                unset($products[$productId]);
                array_unshift($products, $productItem);
            }

            unset($details['product_id']);
        }

        // Remove ID keys
        $products = array_values($products);

        $details['products'] = $products;

        $result->setDetails($details);

        return $result;
    }

    /**
     * Tracks product display
     *
     * @param SaleableInterface $product
     * @return self
     */
    public function recordProduct(SaleableInterface $product): self
    {
        $details = $this->event->getDetails();
        if (isset($details['products'][$product->getId()])) {
            return $this;
        }

        $productDetails = [
            'product_id' => $product->getId(),
        ];

        /** @var FinalPrice $finalPriceModel */
        $finalPriceModel = $product->getPriceInfo()->getPrice(
            FinalPrice::PRICE_CODE);

        /** @var float $min */
        $min = $finalPriceModel->getMinimalPrice()->getValue();
        /** @var float $max */
        $max = $finalPriceModel->getMaximalPrice()->getValue();
        $productDetails['price'] = $min == $max
            ? $min
            : compact('min', 'max');

        /** @var float $min */
        $min = $finalPriceModel->getMinimalPrice()->getAdjustmentAmount('tax');
        if (false !== $min) {
            /** @var float $max */
            $max = $finalPriceModel->getMaximalPrice()->getAdjustmentAmount('tax');
            $productDetails['tax_amount'] = $min == $max
                ? $min
                : compact('min', 'max');
        }

        /** @var Store $store */
        try {
            $store = $this->storeManager->getStore();
            $productDetails['currency_code'] = $store->getCurrentCurrencyCode();
        } catch (NoSuchEntityException $e) {
            // Just do not set currency code
        }

        $details['products'][$product->getId()] = $productDetails;
        $this->event->setDetails($details);

        return $this;
    }

    //
    // Getters and setters
    //

    /**
     * Indicates if page record is enabled and started
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->event? true : false;
    }

    /**
     * @return \AristanderAi\Aai\Model\Event
     */
    public function getEvent()
    {
        return $this->event;
    }
}