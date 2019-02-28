<?php
namespace AristanderAi\Aai\Block\Adminhtml\System\Config;

use AristanderAi\Aai\Helper\Data;
use Magento\Backend\Block\Template\Context;

class RestorePrices extends FullRow
{
    /**
     * @inheritdoc
     * @return string
     */
    public function getTemplate()
    {
        return 'system/config/restore-prices.phtml';
    }
}
