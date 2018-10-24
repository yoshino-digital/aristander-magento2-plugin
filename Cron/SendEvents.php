<?php
namespace AristanderAi\Aai\Cron;

use AristanderAi\Aai\Cron\SendEvents\Exception;
use AristanderAi\Aai\Helper\ApiHttpClient;
use AristanderAi\Aai\Helper\ApiHttpClient\NotConfiguredException;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Model\Event;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Model\ResourceModel\Event as EventResource;
use AristanderAi\Aai\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class SendEvents
{
    protected $endPointUrl = 'https://api.aristander.ai/event';

    protected $maxCount = 1000;

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    protected $logger;

    /** @var EventCollectionFactory */
    protected $eventCollectionFactory;

    /** @var EventRepository */
    protected $eventRepository;

    /** @var EventResource */
    protected $eventResource;

    /** @var ApiHttpClient */
    protected $helperApiHttpClient;

    /** @var DateTime */
    protected $date;

    /** @var Data */
    protected $helperData;

    /** @var \Zend\Http\Client */
    protected $httpClient;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        EventCollectionFactory $eventCollectionFactory,
        EventRepository $eventRepository,
        EventResource $eventResource,
        ApiHttpClient $helperApiHttpClient,
        DateTime $date,
        Data $helperData
    ) {
        $this->logger = $logger;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventRepository = $eventRepository;
        $this->eventResource = $eventResource;
        $this->helperApiHttpClient = $helperApiHttpClient;
        $this->date = $date;
        $this->helperData = $helperData;
    }

    protected $attributeRename = [
        'type' => 'event_type',
        'details' => 'event_details',
    ];

    /**
     * @throws ApiHttpClient\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        try {
            $this->initHttpClient();
        } catch (NotConfiguredException $e) {
            $this->logger->debug('Aristander.ai event sending is not configured');
            return;
        }

        $this->logger->debug('Starting Aristander.ai event sending');

        $counter = [
            'success' => 0,
            'error' => 0,
        ];

        /** @var \AristanderAi\Aai\Model\ResourceModel\Event\Collection $events */
        $events = $this->eventCollectionFactory->create()
            ->setStatusFilter(['pending', 'error']);
        if (!empty($this->maxCount)) {
            $events->setPageSize($this->maxCount);
        }

        $this->logger->debug("Found {$events->getSize()} pending events");

        /** @var Event $event */
        foreach ($events as $event) {
            try {
                $this->sendEvent($event);
                $event->setStatus('success');
                $event->setLastError(null);
                $counter['success']++;
            } catch (Exception $e) {
                $event->setStatus('error');
                $event->setLastError($e->getMessage());
                $counter['error']++;
            }

            $event->setSyncedAt($this->date->gmtDate());

            try {
                $this->eventRepository->save($event);
            } catch (\Exception $e) {
                $this->logger->error("Error saving event #{$event->getId()}: {$e->getMessage()}");
            }
        }

        $this->logger->debug('Cleaning old synced events');
        $this->eventResource->cleanUp();

        $this->logger->debug("Finished Aristander.ai event sending. Success: {$counter['success']}, error: {$counter['error']}");
    }

    /**
     * @param Event $event
     * @throws Exception
     */
    protected function sendEvent(Event $event)
    {
        $data = $event->toArray(Event::getExportAttributes());
        $request = [];
        foreach ($data as $key => $value) {
            if (isset($this->attributeRename[$key])) {
                $key = $this->attributeRename[$key];
            }

            $request[$key] = $value;
        }

        $this->httpClient->setRawBody(json_encode($request));

        try {
            $this->httpClient->send();
            $response = $this->httpClient->getResponse();
            if (!$response->isOk()) {
                throw new Exception("API error {$response->getStatusCode()}: {$response->getBody()}");
            }
        } /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        catch (\Zend\Http\Exception\RuntimeException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Initializes HTTP client object
     *
     * @throws ApiHttpClient\NotConfiguredException
     * @throws ApiHttpClient\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function initHttpClient()
    {
        $this->httpClient = $this->helperApiHttpClient->init([
            'url' => $this->helperData->getConfigValue('api/send_events')
                ?? $this->endPointUrl,
        ]);
    }
}