<?php
namespace AristanderAi\Aai\Service\PollApi;

use AristanderAi\Aai\Cron\SendEvents\Exception;
use AristanderAi\Aai\Helper\PollApi\HttpClientCreator;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Model\Event;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Model\ResourceModel\Event as EventResource;
use AristanderAi\Aai\Model\ResourceModel\Event\Collection;
use AristanderAi\Aai\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class SendEvents
{
    private $endPointUrl = 'https://api.aristander.ai/events';

    private $maxCount = 100;

    /** @var \Zend\Http\Client */
    private $httpClient;

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    private $logger;

    /** @var EventCollectionFactory */
    private $eventCollectionFactory;

    /** @var EventRepository */
    private $eventRepository;

    /** @var EventResource */
    private $eventResource;

    /** @var HttpClientCreator */
    private $httpClientCreator;

    /** @var DateTime */
    private $date;

    /** @var Data */
    private $helperData;

    /** @var ResourceConnection */
    private $resource;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        EventCollectionFactory $eventCollectionFactory,
        EventRepository $eventRepository,
        EventResource $eventResource,
        HttpClientCreator $httpClientCreator,
        DateTime $date,
        Data $helperData,
        ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventRepository = $eventRepository;
        $this->eventResource = $eventResource;
        $this->httpClientCreator = $httpClientCreator;
        $this->date = $date;
        $this->helperData = $helperData;
        $this->resource = $resource;
    }

    /**
     * @throws HttpClientCreator\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws Exception
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai event sending process');

        try {
            $this->initHttpClient();
        } catch (HttpClientCreator\NotConfiguredException $e) {
            $this->logger->debug($e->getMessage());
            return;
        }

        /** @var Collection $eventCollection */
        $eventCollection = $this->eventCollectionFactory->create()
            ->setStatusFilter(['pending', 'error']);
        if (!empty($this->maxCount)) {
            $eventCollection->setPageSize($this->maxCount);
        }

        if (0 != $eventCollection->getSize()) {
            $this->logger->debug("Found {$eventCollection->getSize()} pending events");

            $pageCount = $eventCollection->getLastPageNumber();
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $this->logger->debug("Processing page #{$pageNo} of {$eventCollection->getLastPageNumber()}");

                $events = $this->exportEvents($eventCollection);
                if (empty($events)) {
                    $this->logger->debug("Fetched no pending events, stopping page loop");
                    break;
                }

                $this->logger->debug("Fetched events: " . count($events));
                $this->logger->debug(
                    "Sending event page #{$pageNo} of {$eventCollection->getLastPageNumber()}"
                );

                $exception = null;
                $notAcceptedEvents = [];
                try {
                    $status = $this->sendEventPage($events);
                    $notAcceptedEvents = $status['notAcceptedEvents'];
                } catch (Exception $exception) {
                    // Just assign $exception variable
                    $this->logger->error(
                        "Event page sending error: {$exception->getMessage()}"
                    );
                }

                $this->logger->debug('Updating processed event statuses');
                
                //TODO: add transaction

                $this->updateEventStatuses(
                    $eventCollection,
                    $notAcceptedEvents,
                    null !== $exception
                        ? $exception->getMessage()
                        : ''
                );

                $this->logger->debug('Event statuses updated');

                if (null !== $exception) {
                    break;
                }
            }
        } else {
            $this->logger->debug("No pending events found so just pinging API");
            $this->sendPingEvent();
        }

        $this->logger->debug('Cleaning old synced events');
        $this->eventResource->cleanUp();

        $this->logger->debug("Finished Aristander.ai event sending");
    }

    /**
     * Sends immediate ping event
     * (consider a refactoring to move it out of cron task class as it's not
     * really belong here)
     */
    public function ping()
    {
        try {
            $this->initHttpClient();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return;
        }

        try {
            $this->sendPingEvent();
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
    }

    private function exportEvents(Collection $eventCollection)
    {
        // Force fetching top pending events
        $eventCollection->clear();
        // The loop doesn't call setCurPage() because changing status to
        // "success" moves events out of collection

        $result = [];
        /** @var Event $event */
        foreach ($eventCollection as $event) {
            $result[] = $event->export();
        }

        return $result;
    }

    /**
     * @param array $events
     * @return array
     * @throws Exception
     */
    private function sendEventPage(array $events)
    {
        $notAcceptedEvents = [];

        $response = $this->sendEvents($events);
        if (isset($response['event_messages'])) {
            $notAcceptedEvents = $response['event_messages'];
        }
        $count = [
            'accepted' => $response['n_valid_events'],
            'not-accepted' => count($notAcceptedEvents),
        ];
        $this->logger->debug(
            "Event page sent OK. Accepted events: {$count['accepted']}. "
            . "Invalid events: {$count['not-accepted']}."
        );

        return compact('notAcceptedEvents');
    }

    /**
     * @param array $events
     * @return array Decoded response
     * @throws Exception
     */
    private function sendEvents(array $events)
    {
        $this->httpClient->setRawBody(json_encode(compact('events')));

        try {
            $response = $this->httpClient->send();
            if (!$response->isOk()) {
                throw new Exception(__(
                    'API error %1: %2',
                    [$response->getStatusCode(), $response->getBody()]
                ));
            }
        } catch (\Zend\Http\Exception\RuntimeException $e) {
            throw new Exception(__(
                'Error sending API request: %1',
                [$e->getMessage()]
            ));
        }

        $response = json_decode($response->getBody(), true);
        if (false === $response) {
            throw new Exception(__(
                'Error decoding JSON response: %1',
                [$response->getBody()]
            ));
        }

        /** @var array $response */
        return $response;
    }

    /**
     * @throws Exception
     */
    private function sendPingEvent()
    {
        $this->sendEvents([
            [
                'event_type' => 'ping',
                'event_details' => [],
                'timestamp' => time(),
            ],
        ]);
    }

    /**
     * @param Collection $eventCollection
     * @param array $notAcceptedEvents
     * @param $errorMsg
     * @throws Exception
     */
    private function updateEventStatuses(
        Collection $eventCollection,
        array $notAcceptedEvents,
        $errorMsg
    ) {
        $syncDate = $this->date->gmtDate();

        /** @var Event $event */
        foreach (array_values($eventCollection->getItems()) as $i => $event) {
            if (isset($notAcceptedEvents[$i])) {
                $event->setStatus('not-accepted');
                $event->setLastError($notAcceptedEvents[$i]);
                $event->setSyncedAt($syncDate);
            } elseif ('' == $errorMsg) {
                $event->setStatus('success');
                $event->setLastError(null);
                $event->setSyncedAt($syncDate);
            } else {
                $event->setStatus('error');
                $event->setLastError($errorMsg);
            }
        }

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();

        try {
            $eventCollection->walk([$this->eventRepository, 'save']);
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new Exception(__(
                'Error saving events: %1',
                [$e->getMessage()]
            ));
        }

        $connection->commit();
    }

    /**
     * Initializes HTTP client object
     *
     * @throws HttpClientCreator\NotConfiguredException
     * @throws HttpClientCreator\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\ValidatorException
     */
    private function initHttpClient()
    {
        $this->httpClient = $this->httpClientCreator->create([
            'url' => $this->helperData->getConfigValue('api/send_events')
                ?: $this->endPointUrl,
        ]);
    }
}
