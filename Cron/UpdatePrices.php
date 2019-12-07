<?php
namespace AristanderAi\Aai\Cron;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\Price;
use \AristanderAi\Aai\Service\PollApi\ImportPrices as Service;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class UpdatePrices
{
    /** @var int Minimum interval, interval step and cron job frequency in minutes */
    private $intervalTick = 30;

    /** @var int Maximum interval in minutes */
    private $baseInterval = 10080; //week

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    private $logger;

    /** @var Data */
    private $helperData;

    /** @var Price */
    private $helperPrice;

    /** @var Service */
    private $importPrices;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        Data $helperData,
        Price $helperPrice,
        Service $importPrices
    ) {
        $this->logger = $logger;
        $this->helperData = $helperData;
        $this->helperPrice = $helperPrice;
        $this->importPrices = $importPrices;
    }

    /**
     * Implements price import cron job
     *
     * @throws \AristanderAi\Aai\Service\PollApi\ImportPrices\Exception
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai price update cron job');

        // Decide on execution skip
        $updateInterval = (int) $this->helperData->getConfigValue(
            'price/update_interval'
        );
        if ($updateInterval != $this->intervalTick) {
            // Number of granular intervals passed since max interval start
            $ticksSinceBaseIntervalStart = floor(
                (time() % ($this->baseInterval * 60))
                /
                ($this->intervalTick * 60)
            );
            $updateIntervalTicks = $updateInterval / $this->intervalTick;

            $tickSkipped = $ticksSinceBaseIntervalStart % $updateIntervalTicks;
            if (0 != $tickSkipped) {
                $this->logger->debug("Aristander.ai price update is configured to run once per {$updateIntervalTicks} calls. Skipping call {$tickSkipped} of {$updateIntervalTicks}.");
                return;
            }
        }

        if ($this->helperData->isPriceImportEnabled())  {
            $this->importPrices->execute();
        } else {
            $this->logger->debug('Aristander.ai price import is disabled');
        }

        if ('timeseries' == $this->helperPrice->getMode()) {
            $this->helperPrice->updateAlternativePriceFlag();
        }

        $this->logger->debug('Finished Aristander.ai price update cron job');
    }
}
