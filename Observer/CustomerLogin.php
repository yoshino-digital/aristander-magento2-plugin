<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\LoginRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
{
    /** @var Data */
    protected $helperData;

    /** @var LoginRecorder */
    protected $loginRecorder;

    public function __construct(
        Data $helperData,
        LoginRecorder $loginRecorder
    ) {
        $this->helperData = $helperData;
        $this->loginRecorder = $loginRecorder;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('login')) {
            return;
        }

        $this->loginRecorder->record();
    }
}