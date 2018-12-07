<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\PageRecorder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class StartPageRecord implements ObserverInterface
{
    /** @var Data */
    private $helperData;

    /** @var PageRecorder */
    private $pageRecorder;

    public function __construct(
        Data $helperData,
        PageRecorder $pageRecorder
    ) {
        $this->helperData = $helperData;
        $this->pageRecorder = $pageRecorder;
    }

    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('page')) {
            return;
        }

        /** @var Http $request */
        $request = $observer->getData('request');

        if ($request->isAjax()) {
            return;
        }

        $this->pageRecorder->start();
        if (!$this->pageRecorder->isStarted()) {
            return;
        }

        $event = $this->pageRecorder->getEvent();

        $path = explode('/', trim($request->getPathInfo(), '/'));
        $details = $event->getDetails();

        if (1 == count($path) && '' == $path[0]) {
            // Home page
            $details['page_name'] = 'home';
        } elseif ('catalog' == $path[0]
            && 'product' == $path[1]
            && 'view' == $path[2]
        ) {
            // Product view page
            $details['page_name'] = 'product_page';

            $details['product_id'] = $request->getParam('id');
        } elseif (2 == count($path) && 'checkout' == $path[0] && 'cart' == $path[1]) {
            // Cart page
            $details['page_name'] = 'basket';
        } elseif (('checkout' == $path[0]) || ('multishipping' == $path[0] && 'checkout' == $path[1])) {
            // Cart page
            $details['page_name'] = 'checkout';
        }

        $details['page_url'] = $request->getUri()->toString();

        $event->setDetails($details);
    }
}
