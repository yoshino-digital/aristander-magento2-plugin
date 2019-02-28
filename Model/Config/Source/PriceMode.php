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
        return array(
            array(
                'value' => 'original',
                'label' => __('Original prices'),
            ),
            array(
                'value' => 'alternative',
                'label' => __('Aristander prices'),
            ),
            array(
                'value' => 'split',
                'label' => __('A/B price testing'),
            ),
        );
    }
}
