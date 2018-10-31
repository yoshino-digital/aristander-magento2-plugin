<?php

namespace AristanderAi\Aai\Controller\Track;

use AristanderAi\Aai\Controller\Track\Page\ValidationException;
use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Page extends Action
{
    /** @var EventFactory */
    protected $eventFactory;

    /** @var EventRepository */
    protected $eventRepository;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        JsonFactory $resultJsonFactory
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->resultJsonFactory = $resultJsonFactory;

        return parent::__construct($context);
    }

    public function execute()
	{
	    $result = $this->resultJsonFactory->create();

        $event = $this->eventFactory->create(['type' => 'page']);
        if (!$event->isEnabled()) {
            $result->setData(['status' => 'disabled']);
            return $result;
        }

        $details = $this->getRequest()->getParam('details');

        try {
            if (empty($details)) {
                throw new ValidationException("Parameter 'details' is missing or not array");
            }

            $details = base64_decode($details);
            if (FALSE === $details) {
                throw new ValidationException("Error decoding 'details' parameter");
            }

            $details = unserialize($details);
            if (!is_array($details)) {
                throw new ValidationException("Error extracting 'details' parameter");
            }

        } catch (ValidationException $e) {
            /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            $result->setHttpResponseCode(
                \Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST)
                ->setData([
                    'status' => 'error',
                    'error' => $e->getMessage()
                ]);
            return $result;
        }

        $event->collectGeneralProperties()
            ->setDetails($details);

        try {
            $this->eventRepository->save($event);
        } catch (\Exception $e) {
            /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            $result->setHttpResponseCode(
                \Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR)
                ->setData('error',
                    "Error saving event data");
        }

        $result->setData(['status' => 'success']);

        return $result;
    }
}