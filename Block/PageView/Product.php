<?php

namespace AristanderAi\Aai\Block\PageView;

use Magento\Framework\View\Element\Template;

class Product extends Template
{
    protected $_template = 'AristanderAi_Aai::page-view/product.phtml';

    public function setDetails(array $value)
    {
        return $this->setData('details', $value);
    }

    public function getDetails()
    {
        return $this->getData('details');
    }
}