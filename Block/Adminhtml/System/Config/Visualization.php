<?php
namespace AristanderAi\Aai\Block\Adminhtml\System\Config;

use AristanderAi\Aai\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Visualization extends Field
{
    /** @var string @Template path */
    protected $_template = 'system/config/visualization.phtml';

    /** @var string Image URL template. Use {apiKey} placeholder to specify the API key position */
    protected $imageUrlTemplate = 'https://staging.api.aristander.ai/visualization/{apiKey}.png';

    /** @var string Link URL template. Use {apiKey} placeholder to specify the API key position */
    protected $linkUrlTemplate = 'https://aristander.ai/app';

    protected $helperData;

    public function __construct(
        Context $context,
        Data $helperData,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $columns = $this->getRequest()->getParam('website') || $this->getRequest()->getParam('store') ? 5 : 4;
        return $this->_decorateRowHtml($element, "<td colspan='{$columns}'>" . $this->toHtml() . '</td>');
    }

    public function getTemplate()
    {
        if (empty($this->getApiKey())) {
            // Folder named same as main template
            return pathinfo($this->_template, PATHINFO_DIRNAME)
                . '/'
                . pathinfo($this->_template, PATHINFO_FILENAME)
                . '/no-api-key.phtml';
        }

        return parent::getTemplate();
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

    protected function prepareUrl($url)
    {
        return str_replace('{apiKey}', $this->getApiKey(), $url);
    }
}