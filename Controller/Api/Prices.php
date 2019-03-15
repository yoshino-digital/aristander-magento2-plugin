<?php

namespace AristanderAi\Aai\Controller\Api;

use AristanderAi\Aai\Controller\Api;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\Deferred;
use AristanderAi\Aai\Helper\PushApi;
use AristanderAi\Aai\Service\PollApi\ImportPrices;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Prices extends Api
{
    /** @var PushApi */
    private $helperPushApi;

    /** @var Data */
    private $helperData;

    /** @var Deferred */
    private $helperDeferred;

    /** @var ImportPrices */
    private $importPrices;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PushApi $helperPushApi,
        Data $helperData,
        Deferred $helperDeferred,
        ImportPrices $importPrices
    ) {
        $this->helperPushApi = $helperPushApi;

        $this->helperData = $helperData;
        $this->helperDeferred = $helperDeferred;
        $this->importPrices = $importPrices;

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

        if (!$this->helperData->isPriceImportEnabled()) {
            $this->addError(array(
                'code' => 'disabled',
                'title' => 'Price import is disabled by module settings',
            ));
            return null;
        }

        $this->helperDeferred->add([
            $this->importPrices,
            'execute'
        ]);

        return $this->generateResponse();
    }
}
