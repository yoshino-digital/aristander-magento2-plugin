<?php
namespace AristanderAi\Aai\Block\Adminhtml\System\Config;

use AristanderAi\Aai\Helper\Data;
use Magento\Backend\Block\Template\Context;

class RestorePrices extends FullRow
{
    /** @var Data */
    private $helperData;

    public function __construct(
        Context $context,
        Data $helperData,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * Generates URL for given controller action
     *
     * @param string $action
     * @return string
     */
    public function getActionUrl($action = 'index')
    {
        return $this->getUrl("aristander-ai/restorePrices/{$action}");
    }

    /**
     * Gets price backup status
     * @return string|null
     */
    public function getStatus()
    {
        return $this->helperData->getConfigValue(
            'price_backup/status'
        );
    }

    /**
     * @inheritdoc Dynamically returns no-backup template for empty status
     * @return string
     */
    public function getTemplate()
    {
        return !empty($this->getStatus())
            ? 'system/config/restore-prices.phtml'
            : 'system/config/restore-prices/no-backup.phtml';
    }
}
