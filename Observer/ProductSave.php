<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Price;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSave implements ObserverInterface
{
    /** @var Price */
    private $helperPrice;

    public function __construct(
        Price $helperPrice
    ) {
        $this->helperPrice = $helperPrice;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getData('product');
        if ($product->dataHasChangedFor('tier_price')) {
            $this->helperPrice->handleProductAlternativePriceUpdate($product);
        }
    }
}
