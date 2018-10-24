<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\CartRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CartSaveAfter implements ObserverInterface
{
    /** @var Data */
    protected $helperData;

    /** @var CartRecorder */
    protected $cartRecorder;

    public function __construct(
        Data $helperData,
        CartRecorder $cartRecorder
    ) {
        $this->helperData = $helperData;
        $this->cartRecorder = $cartRecorder;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('basket')) {
            return;
        }

        $this->cartRecorder->saveEvents();
    }
}