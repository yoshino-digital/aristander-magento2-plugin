<?php

namespace AristanderAi\Aai\Ui\Component\Listing\Columns;

use AristanderAi\Aai\Helper\Price as PriceHelper;
use Magento\Catalog\Ui\Component\Listing\Columns\Price;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;

class AlternativePrice extends Price
{
    /** @var PriceHelper */
    private $priceHelper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CurrencyInterface $localeCurrency,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $localeCurrency,
            $storeManager,
            $components,
            $data
        );

        $this->priceHelper = $priceHelper;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (isset($dataSource['data']['items'])) {
            $preferAlternative = $this->priceHelper->getAlternativePriceSwitch();

            foreach ($dataSource['data']['items'] as & $item) {

                $item['activePrice'] = $preferAlternative && !empty($item['aai_alternative_price'])
                    ? 'alternative'
                    : 'original';
            }
        }

        return $dataSource;
    }
}