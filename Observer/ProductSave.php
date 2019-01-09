<?php
namespace AristanderAi\Aai\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSave implements ObserverInterface
{
    public $enabled = true;

    public function execute(Observer $observer)
    {
        // Do not trigger on import
        if (!$this->enabled) {
            return;
        }

        /** @var Product $product */
        $product = $observer->getData('product');
        if ($product->dataHasChangedFor('price')) {
            $product->setData('aai_backup_price', null);
        }
    }
}
