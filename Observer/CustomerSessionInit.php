<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Price;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerSessionInit implements ObserverInterface
{
    /** @var Price */
    private $helperPrice;

    public function __construct(
        Price $helperPrice
    ) {
        $this->helperPrice = $helperPrice;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function execute(Observer $observer)
    {
        $this->helperPrice->initCustomerSession(
            $observer->getData('customer_session')
        );
    }
}
