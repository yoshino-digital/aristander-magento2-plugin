<?php
namespace AristanderAi\Aai\Plugin\Adminhtml\Customer\Group;

use AristanderAi\Aai\Helper\Price;
use Magento\Customer\Ui\Component\Listing\Column\GroupActions;

class GroupActionsPlugin
{
    /** @var Price */
    private $helperPrice;

    public function __construct(
        Price $helperPrice
    ) {
        $this->helperPrice = $helperPrice;
    }

    /**
     * Removes delete action for alternative price customer group
     *
     * @param GroupActions $subject
     * @param array $result
     * @return array
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function afterPrepareDataSource(
        GroupActions $subject,
        array $result
    ) {
        $groupId = $this->helperPrice->getCustomerGroupId();

        if (isset($result['data']['items'])) {
            foreach ($result['data']['items'] as & $item) {
                if ($groupId != $item['customer_group_id']) {
                    continue;
                }

                unset($item[$subject->getData('name')]['delete']);
                break;
            }
        }

        return $result;
    }
}
