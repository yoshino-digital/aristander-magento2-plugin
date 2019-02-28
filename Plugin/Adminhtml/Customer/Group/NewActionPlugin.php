<?php
namespace AristanderAi\Aai\Plugin\Adminhtml\Customer\Group;

use AristanderAi\Aai\Helper\Price;
use Magento\Backend\Model\View\Result\Page;
use Magento\Customer\Controller\Adminhtml\Group\NewAction;


class NewActionPlugin
{
    /** @var Price */
    private $helperPrice;

    public function __construct(
        Price $helperPrice
    ) {
        $this->helperPrice = $helperPrice;
    }

    /**
     * Removes delete button and sets code field to read-only for alternative
     * price customer group
     *
     * @param NewAction $subject
     * @param Page $result
     * @return Page
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function afterExecute(
        NewAction $subject,
        Page $result
    ) {

        /** @var Page $result */

        if ($this->helperPrice->getCustomerGroupId()
            != $subject->getRequest()->getParam('id')
        ) {
            return $result;
        }

        /** @var \Magento\Customer\Block\Adminhtml\Group\Edit $block */
        $block = $result->getLayout()->getBlock('group');
        if ($block) {
            $block->removeButton('delete');
        }

        /** @var \Magento\Customer\Block\Adminhtml\Group\Edit\Form $block */
        $block = $result->getLayout()->getBlock('group.form');
        if ($block) {
            $element = $block->getForm()->getElement('customer_group_code');
            if ($element) {
                $element->setReadOnly(true)
                    ->setData('note', __('This group is reserved by Aristander module and its name could not be changed'));
            }
        }

        return $result;
    }
}
