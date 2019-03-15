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
                'value' => 'original',
                'label' => __('Original prices'),
            ],
            [
                'value' => 'alternative',
                'label' => __('Aristander prices'),
            ],
            [
                'value' => 'split',
                'label' => __('A/B price testing'),
            ],
        ];
    }
}
