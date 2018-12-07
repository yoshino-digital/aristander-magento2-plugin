<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Block\PageView\Product as ProductBlock;
use AristanderAi\Aai\Block\PageView\ProductFactory;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Model\Event;
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
    /**
     * @var array stores IDs of recorded products to avoid double processing
     */
    private $recordedProducts;

    /** @var Event */
    private $event;

    /** @var EventFactory */
    private $eventFactory;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Session */
    private $session;

    /** @var ProductFactory */
    private $productBlockFactory;

    /** @var Data */
    private $helperData;

    public function __construct(
        EventFactory $eventFactory,
        StoreManagerInterface $storeManager,
        Session $session,
        ProductFactory $productBlockFactory,
        Data $helperData
    ) {
        $this->eventFactory = $eventFactory;
        $this->storeManager = $storeManager;
        $this->session = $session;
        $this->productBlockFactory = $productBlockFactory;
        $this->helperData = $helperData;
    }

    /**
     * Starts tracking page view event
     *
     * @return self
     */
    public function start()
    {
        $this->event = $this->eventFactory->create(['type' => 'page']);
        $this->event->collect();
        $this->recordedProducts = [];

        return $this;
    }

    /**
     * Finalizes page record data collection and returns event object
     *
     * @return Event|null
     */
    public function exportEvent()
    {
        if (!$this->isStarted()) {
            return null;
        }

        $result = clone $this->event;
        $details = $result->getDetails();

        // Workaround for cart and checkout pages not registering products
        if (isset($details['page_name'])
            && in_array($details['page_name'], ['basket', 'checkout'])
        ) {
            // Collect cart items from quote
            $products = [];
            /** @var Item $item */
            foreach ($this->session->getQuote()->getAllVisibleItems() as $item) {
                $product = $item->getProduct();
                $products[$product->getId()] = $this->extractProductDetails($product);
            }

            $details['products'] = $products;
            $result->setDetails($details);
        }

        return $result;
    }

    /**
     * Tracks product display
     *
     * @param SaleableInterface $product
     * @return string|null
     */
    public function recordProduct(SaleableInterface $product)
    {
        if (in_array($product->getId(), $this->recordedProducts)) {
            return null;
        }

        /** @var ProductBlock $block */
        $block = $this->productBlockFactory->create();
        $block->setDetails($this->extractProductDetails($product));
        $result = $block->toHtml();

        $this->recordedProducts[] = $product->getId();

        return $result;
    }

    /**
     * @param array $products
     * @return self
     */
    public function recordProducts(array $products)
    {
        $details = $this->event->getDetails();
        // Workaround for cart and checkout pages not registering products
        if (empty($products) && isset($details['products'])) {
            $products = $details['products'];
        }

        $details['products'] = [];

        if (isset($details['product_id']) && count($products) > 1) {
            $productId = $details['product_id'];
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

        $this->event->setDetails($details);

        return $this;
    }

    /**
     * Extracts required details from a given product
     *
     * @param SaleableInterface $product
     * @return array
     */
    public function extractProductDetails(SaleableInterface $product)
    {
        $result = [
            'product_id' => (string) $product->getId(),
        ];

        /** @var FinalPrice $finalPriceModel */
        $finalPriceModel = $product->getPriceInfo()->getPrice(
            FinalPrice::PRICE_CODE
        );

        /** @var float $min */
        $min = $this->helperData->formatPrice(
            $finalPriceModel->getMinimalPrice()->getValue()
        );
        /** @var float $max */
        $max = $this->helperData->formatPrice(
            $finalPriceModel->getMaximalPrice()->getValue()
        );
        $result['price'] = $min == $max
            ? $min
            : compact('min', 'max');

        /** @var float $min */
        $min = $finalPriceModel->getMinimalPrice()->getAdjustmentAmount('tax');
        if (false !== $min) {
            $min = $this->helperData->formatPrice($min);
            /** @var float $max */
            $max = $this->helperData->formatPrice(
                $finalPriceModel->getMaximalPrice()->getAdjustmentAmount('tax')
            );
            $result['tax_amount'] = $min == $max
                ? $min
                : compact('min', 'max');
        }

        /** @var Store $store */
        try {
            $store = $this->storeManager->getStore();
            $result['currency_code'] = $store->getCurrentCurrencyCode();
        } catch (NoSuchEntityException $e) {
            // Just do not set currency code
        }

        return $result;
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
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param Event $event
     * @return self
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }
}
