<?php
namespace AristanderAi\Aai\Cron;

use AristanderAi\Aai\Helper\Data;
use \AristanderAi\Aai\Service\PollApi\SendEvents as Service;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class SendEvents
{
    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    private $logger;

    /** @var Data */
    private $helperData;

    /** @var Service */
    private $service;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        Data $helperData,
        Service $service
    ) {
        $this->logger = $logger;
        $this->helperData = $helperData;
        $this->service = $service;
    }

    /**
     * Implements cron task
     * 
     * @throws SendEvents\Exception
     * @throws \AristanderAi\Aai\Helper\PollApi\HttpClientCreator\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai event sending cron job');

        // Disabling event tracking doesn't disable event sending so that
        // the module could pass pending events recorded in DB before
        // event tracking was disabled

        $this->service->execute();
        
        $this->logger->debug("Finished Aristander.ai event sending cron job");
    }
}
