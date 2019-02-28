<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Price;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class PriceInit implements ObserverInterface
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
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function execute(Observer $observer)
    {
        switch ($observer->getEvent()->getName()) {
            case 'catalog_product_get_final_price':
                $this->initProduct($observer->getData('product'));
                break;

            case 'catalog_product_collection_load_after':
                $this->initCollection($observer->getData('collection'));
                break;
        }
    }

    /**
     * @param Product $product
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    private function initProduct(Product $product)
    {
        $this->helperPrice->initProductPrice($product);
    }

    /**
     * @param Collection $collection
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    private function initCollection(Collection $collection)
    {
        /** @var Product $product */
        foreach ($collection->getItems() as $product) {
            $this->initProduct($product);
        }
    }
}
