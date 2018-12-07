<?php

namespace AristanderAi\Aai\Block\PageView;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Product extends Template
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setDetails(array $value)
    {
        return $this->setData('details', $value);
    }

    public function getDetails()
    {
        return $this->getData('details');
    }

    public function getTemplate()
    {
        return 'AristanderAi_Aai::page-view/product.phtml';
    }
}
