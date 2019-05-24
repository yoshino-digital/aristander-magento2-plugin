<?php
namespace AristanderAi\Aai\Cron;

use AristanderAi\Aai\Helper\Data;
use \AristanderAi\Aai\Service\PollApi\ImportPrices as Service;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class ImportPrices
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
     * Implements price import cron job
     *
     * @throws \AristanderAi\Aai\Service\ImportPrices\Exception
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai price import cron job');

        if (!$this->helperData->isPriceImportEnabled()) {
            $this->logger->debug('Aristander.ai price import is disabled');
            return;
        }

        $this->service->execute();

        $this->logger->debug('Finished Aristander.ai price import cron job');
    }
}
