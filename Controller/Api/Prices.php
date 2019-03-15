<?php

namespace AristanderAi\Aai\Controller\Api;

use AristanderAi\Aai\Controller\Api;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\Deferred;
use AristanderAi\Aai\Helper\PushApi;
use AristanderAi\Aai\Service\PollApi\SendEvents;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Prices extends Api
{
    /** @var Data */
    private $helperData;

    /** @var Deferred */
    private $helperDeferred;

    /** @var SendEvents */
    private $sendEvents;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PushApi $helperPushApi,
        Data $helperData,
        Deferred $helperDeferred,
        SendEvents $sendEvents
    ) {
        $this->helperData = $helperData;
        $this->helperDeferred = $helperDeferred;
        $this->sendEvents = $sendEvents;

        parent::__construct(
            $context,
            $resultJsonFactory,
            $helperPushApi
        );
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json|null
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        if (!$this->authenticate()) {
            return null;
        }

        // TODO: Implement execute() method.

        return $this->generateResponse();
    }
}
