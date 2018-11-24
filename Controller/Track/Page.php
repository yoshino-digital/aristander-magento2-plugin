<?php

namespace AristanderAi\Aai\Controller\Track;

use AristanderAi\Aai\Controller\Track\Page\ValidationException;
use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Service\PageRecorder;
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

    /** @var PageRecorder */
    protected $pageRecorder;

    public function __construct(
        Context $context,
        EventFactory $eventFactory,
        EventRepository $eventRepository,
        JsonFactory $resultJsonFactory,
        PageRecorder $pageRecorder
    ) {
        $this->eventFactory = $eventFactory;
        $this->eventRepository = $eventRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->pageRecorder = $pageRecorder;

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
        $products = $this->getRequest()->getParam('products');

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

            $indexedProducts = [];
            if (!empty($products)) {
                if (!is_array($products)) {
                    throw new ValidationException("Parameter 'products' is not array");
                }

                foreach ($products as $key => $value) {
                    $value = base64_decode($value);
                    if (false === $value) {
                        throw new ValidationException("Error decoding product #{$key}");
                    }
                    $value = unserialize($value);
                    if (!is_array($value)) {
                        throw new ValidationException("Error extracting product #{$key}");
                    }
                    if (!isset($value['product_id'])) {
                        throw new ValidationException("Invalid product #{$key}: product_id not found");
                    }

                    $indexedProducts[$value['product_id']] = $value;
                }
            }

            $event->collect()
                ->setDetails($details);

            $this->pageRecorder
                ->setEvent($event)
                ->recordProducts($indexedProducts);

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