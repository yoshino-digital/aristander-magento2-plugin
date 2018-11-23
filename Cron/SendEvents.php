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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class SendEvents
{
    protected $endPointUrl = 'https://api.aristander.ai/events';

    protected $maxCount = 100;

    /** @var \Zend\Http\Client */
    protected $httpClient;

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

    /** @var ResourceConnection */
    protected $resource;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        EventCollectionFactory $eventCollectionFactory,
        EventRepository $eventRepository,
        EventResource $eventResource,
        ApiHttpClient $helperApiHttpClient,
        DateTime $date,
        Data $helperData,
        ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventRepository = $eventRepository;
        $this->eventResource = $eventResource;
        $this->helperApiHttpClient = $helperApiHttpClient;
        $this->date = $date;
        $this->helperData = $helperData;
        $this->resource = $resource;
    }

    /**
     * @throws ApiHttpClient\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws Exception
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai event sending task');

        try {
            $this->initHttpClient();
        } catch (NotConfiguredException $e) {
            $this->logger->debug('Aristander.ai event sending is not configured');
            return;
        }

        $this->logger->debug('Starting Aristander.ai event sending');

        /** @var \AristanderAi\Aai\Model\ResourceModel\Event\Collection $eventCollection */
        $eventCollection = $this->eventCollectionFactory->create()
            ->setStatusFilter(['pending', 'error']);
        if (!empty($this->maxCount)) {
            $eventCollection->setPageSize($this->maxCount);
        }

        if (0 != $eventCollection->getSize()) {
            $this->logger->debug("Found {$eventCollection->getSize()} pending events");

            for ($pageNo = 1; $pageNo <= $eventCollection->getLastPageNumber(); $pageNo++) {
                $this->logger->debug("Processing page #{$pageNo} of {$eventCollection->getLastPageNumber()}");

                // Force fetching top pending events
                $eventCollection->clear();
                // The loop doesn't call setCurPage() because changing status to
                // "success" moves events out of collection

                $events = [];
                /** @var Event $event */
                foreach ($eventCollection as $event) {
                    $events[] = $event->export();
                }

                if (!$events) {
                    $this->logger->debug("Fetched no pending events, stopping page loop");
                    break;
                }

                $this->logger->debug("Fetched events: " . count($events));
                $this->logger->debug("Sending event page #{$pageNo} of {$eventCollection->getLastPageNumber()}");

                $syncDate = $this->date->gmtDate();

                $exception = null;
                $notAcceptedEvents = [];
                try {
                    $response = $this->sendEvents($events);
                    if (isset($response['event_messages'])) {
                        $notAcceptedEvents = $response['event_messages'];
                    }
                    $count = array(
                        'accepted' => $response['n_valid_events'],
                        'not-accepted' => count($notAcceptedEvents),
                    );
                    $this->logger->debug("Event page sent OK. Accepted events: {$count['accepted']}. Invalid events: {$count['not-accepted']}.");
                } catch (Exception $exception) {
                    // Just assign $exception variable
                    $this->logger->error("Event page sending error: {$exception->getMessage()}");
                }

                $this->logger->debug("Updating processed event statuses");

                $connection = $this->resource->getConnection();
                $connection->beginTransaction();

                /** @var Event $event */
                foreach (array_values($eventCollection->getItems()) as $i => $event) {
                    if (isset($notAcceptedEvents[$i])) {
                        $event->setStatus('not-accepted');
                        $event->setLastError($notAcceptedEvents[$i]);
                        $event->setSyncedAt($syncDate);
                    } elseif (is_null($exception)) {
                        $event->setStatus('success');
                        $event->setLastError(null);
                        $event->setSyncedAt($syncDate);
                    } else {
                        $event->setStatus('error');
                        $event->setLastError($exception->getMessage());
                    }

                    try {
                        $this->eventRepository->save($event);
                    } catch (\Exception $e) {
                        $this->logger->error("Error saving event #{$event->getId()}: {$e->getMessage()}");
                        $connection->rollBack();
                        throw new Exception($e->getMessage());
                    }
                }

                $connection->commit();

                $this->logger->debug("Event statuses updated OK");

                if (!is_null($exception)) {
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

    /**
     * @param array $events
     * @return array Decoded response
     * @throws Exception
     */
    protected function sendEvents(array $events)
    {
        $this->httpClient->setRawBody(json_encode(compact('events')));

        try {
            $response = $this->httpClient->send();
            if (!$response->isOk()) {
                throw new Exception("API error {$response->getStatusCode()}: {$response->getBody()}");
            }
        } /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        catch (\Zend\Http\Exception\RuntimeException $e) {
            throw new Exception($e->getMessage());
        }

        $response = json_decode($response->getBody(), true);
        if (false === $response) {
            throw new Exception("Error decoding JSON response: {$response->getBody()}");
        }

        /** @var array $response */
        return $response;
    }

    /**
     * @throws Exception
     */
    protected function sendPingEvent()
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