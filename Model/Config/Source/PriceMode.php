<?php
namespace AristanderAi\Aai\Model\Config\Source;

class PriceMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'fixed_original',
                'label' => __('Fixed - Original'),
            ],
            [
                'value' => 'fixed_aristander',
                'label' => __('Fixed - Aristander'),
            ],
            [
                'value' => 'timeseries',
                'label' => __('Timeseries'),
            ],
            [
                'value' => 'split',
                'label' => __('Split'),
            ],
        ];
    }
}
