<?php
namespace AristanderAi\Aai\Plugin\Adminhtml\Customer\Group;

use AristanderAi\Aai\Helper\Price;
use \Magento\Customer\Controller\Adminhtml\Group\NewAction;


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
     * @param NewAction $subject
     * @param $result
     * @return \Magento\Backend\Model\View\Result\Page
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function afterExecute(NewAction $subject, $result)
    {

        /** @var \Magento\Backend\Model\View\Result\Page $result */

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
