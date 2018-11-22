<?php
namespace AristanderAi\Aai\Plugin;

use Magento\Framework\Pricing\Render\PriceBox;
use AristanderAi\Aai\Service\PageRecorder;

class PriceBoxPlugin
{
    /** @var PageRecorder */
    protected $pageRecorder;

    public function __construct(PageRecorder $pageRecorder)
    {
        $this->pageRecorder = $pageRecorder;
    }

    public function beforeRenderAmount(PriceBox $subject)
    {
        if (!$this->pageRecorder->isStarted()) {
            return;
        }

        $this->pageRecorder->recordProduct($subject->getSaleableItem());
    }
}