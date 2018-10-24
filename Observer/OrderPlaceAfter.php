<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\OrderRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderPlaceAfter implements ObserverInterface
{
    /** @var Data */
    protected $helperData;

    /** @var OrderRecorder */
    protected $orderRecorder;

    public function __construct(
        Data $helperData,
        OrderRecorder $orderRecorder
    ) {
        $this->helperData = $helperData;
        $this->orderRecorder = $orderRecorder;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('order')) {
            return;
        }

        $this->orderRecorder->record($observer->getData('order'));
    }
}