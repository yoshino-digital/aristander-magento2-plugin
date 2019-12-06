<?php
namespace AristanderAi\Aai\Model\Config\Source;

class UpdateInterval implements \Magento\Framework\Option\ArrayInterface
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
                'value' => '30',
                'label' => __('30 minutes'),
            ],
            [
                'value' => '60',
                'label' => __('1 hour'),
            ],
            [
                'value' => '120',
                'label' => __('2 hours'),
            ],
            [
                'value' => '1440',
                'label' => __('Once a day'),
            ],
            [
                'value' => '10080',
                'label' => __('Once a week'),
            ]
        ];
    }
}
