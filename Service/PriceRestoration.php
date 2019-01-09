<?php
namespace AristanderAi\Aai\Service;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Observer\ProductSave as ProductSaveObserver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
class PriceRestoration
{
    // Feedback
    public $totalCount;
    public $processedCount;

    /** @var Data */
    private $helperData;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var ProductSaveObserver */
    private $productSaveObserver;

    public function __construct(
        Data $helperData,
        ProductCollectionFactory $productCollectionFactory,
        ProductSaveObserver $productSaveObserver
    ) {
        $this->helperData = $helperData;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productSaveObserver = $productSaveObserver;
    }

    public function execute(array $options = [])
    {
        // Disable price import
        if ($this->helperData->isPriceImportEnabled()) {
            $this->helperData->setPriceImportEnabled(false);
        }

        /** @var ProductCollection $products */
        $products = $this->productCollectionFactory->create();
        $products->addAttributeToSelect([
            'price',
            'aai_backup_price',
        ])
            ->addAttributeToFilter(
                'aai_backup_price',
                ['notnull' => true]
            );

        if (isset($options['maxItemCount'])) {
            $products->setPageSize($options['maxItemCount']);
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $product->setData('price', $product->getData('aai_backup_price'))
                ->setData('aai_backup_price', null);
        }

        //TODO: test observer disabling
        $this->productSaveObserver->enabled = false;
        $products->walk('save');
        $this->productSaveObserver->enabled = true;

        $this->totalCount = $products->getSize();
        $this->processedCount = $products->count();

        return $this;
    }

    /**
     * Sets product backup status (none, created, restoring)
     *
     * @param string|null $status
     * @return $this
     */
    public function setStatus($status = null)
    {
        if ($status != $this->helperData->getConfigValue('price_backup/status')) {
            $this->helperData->setConfigValue('price_backup/status', $status);
        }

        return $this;
    }
}
