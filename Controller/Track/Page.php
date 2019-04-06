<?php

namespace AristanderAi\Aai\Controller\Track;

use AristanderAi\Aai\Controller\Track\Page\ValidationException;
use AristanderAi\Aai\Model\EventFactory;
use AristanderAi\Aai\Model\EventRepository;
use AristanderAi\Aai\Service\EventRecorder\Page as PageRecorder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Page extends Action
{
    /** @var EventFactory */
    private $eventFactory;

    /** @var EventRepository */
    private $eventRepository;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PageRecorder */
    private $pageRecorder;

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

    /**
     * @return \Magento\Framework\Controller\Result\Json
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $event = $this->eventFactory->create(['type' => 'page']);
        if (!$event->isEnabled()) {
            $result->setData(['status' => 'disabled']);
            return $result;
        }

        try {
            $details = $this->getDetails();
            $products = $this->getProducts();

            $event->collect()
                ->setDetails($details);

            $this->pageRecorder
                ->setEvent($event)
                ->saveProducts($products);
        } catch (ValidationException $e) {
            /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST)
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
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR)
                ->setData('error', "Error saving event data");
        }

        $result->setData(['status' => 'success']);

        return $result;
    }

    /**
     * @return array
     * @throws ValidationException
     */
    private function getDetails()
    {
        $result = $this->getRequest()->getParam('details');

        if (empty($result)) {
            throw new ValidationException("Parameter 'details' is missing or not array");
        }

        $result = base64_decode($result);
        if (false === $result) {
            throw new ValidationException("Error decoding 'details' parameter");
        }

        $result = unserialize($result);
        if (!is_array($result)) {
            throw new ValidationException("Error extracting 'details' parameter");
        }

        return $result;
    }

    /**
     * @return array
     * @throws ValidationException
     */
    private function getProducts()
    {
        $products = $this->getRequest()->getParam('products');

        $result = [];
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

                $result[$value['product_id']] = $value;
            }
        }

        return $result;
    }
}
