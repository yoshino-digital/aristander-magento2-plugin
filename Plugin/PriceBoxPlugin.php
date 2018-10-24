<?php
namespace AristanderAi\Aai\Plugin;

use Magento\Framework\Pricing\Render\PriceBox;
use AristanderAi\Aai\Service\PageRecorder;

class PriceBoxPlugin
{
    /** @var PageRecorder */
    protected $pageRecord;

    public function __construct(PageRecorder $pageView)
    {
        $this->pageRecord = $pageView;
    }

    public function beforeRenderAmount(PriceBox $subject)
    {
        $this->pageRecord->recordProduct($subject->getSaleableItem());
    }
}