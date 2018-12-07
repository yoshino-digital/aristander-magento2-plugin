<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\UserCreationRecorder;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerCreate implements ObserverInterface
{
    /** @var bool Saves object status */
    private $isObjectNew;
    
    /** @var Data */
    private $helperData;

    /** @var UserCreationRecorder */
    private $userCreationRecorder;

    public function __construct(
        Data $helperData,
        UserCreationRecorder $userCreationRecorder
    ) {
        $this->helperData = $helperData;
        $this->userCreationRecorder = $userCreationRecorder;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('user_creation')) {
            return;
        }

        /** @var Customer $customer */
        $customer = $observer->getData('customer');

        switch ($observer->getEvent()->getName()) {
            case 'customer_save_before':
                $this->isObjectNew = $customer->isObjectNew();
                break;

            case 'customer_save_after':
                if (!$this->isObjectNew) {
                    // Ignore existing customer
                    return;
                }

                $this->userCreationRecorder->record($customer->getId());
                break;
        }
    }
}
