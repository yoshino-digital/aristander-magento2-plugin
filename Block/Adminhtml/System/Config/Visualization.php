<?php
namespace AristanderAi\Aai\Block\Adminhtml\System\Config;

use AristanderAi\Aai\Helper\Data;
use Magento\Backend\Block\Template\Context;

class Visualization extends FullRow
{
    /** @var string Image URL template. Use {apiKey} placeholder to specify the API key position */
    private $imageUrlTemplate = 'https://api.aristander.ai/visualization/{apiKey}.png';

    /** @var string Link URL template. Use {apiKey} placeholder to specify the API key position */
    private $linkUrlTemplate = 'https://aristander.ai/app';

    /** @var Data */
    private $helperData;

    public function __construct(
        Context $context,
        Data $helperData,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    public function getTemplate()
    {
        return !empty($this->getApiKey())
            ? 'system/config/visualization.phtml'
            : 'system/config/visualization/no-api-key.phtml';
    }

    public function getApiKey()
    {
        return $this->helperData->getConfigValue('general/api_key');
    }

    public function getImageUrl()
    {
        return $this->prepareUrl($this->imageUrlTemplate);
    }

    public function getLinkUrl()
    {
        return $this->prepareUrl($this->linkUrlTemplate);
    }

    private function prepareUrl($url)
    {
        return str_replace('{apiKey}', $this->getApiKey(), $url);
    }
}
