<?php

namespace AristanderAi\Aai\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Base class for full row custom system config elements
 *
 * Class FullRow
 * @package AristanderAi\Aai\Block\Adminhtml\System\Config
 */
abstract class FullRow extends Field
{
    public function render(AbstractElement $element)
    {
        $columns = $this->getRequest()->getParam('website') || $this->getRequest()->getParam('store') ? 5 : 4;
        return $this->_decorateRowHtml($element, "<td colspan='{$columns}'>" . $this->toHtml() . '</td>');
    }
}
