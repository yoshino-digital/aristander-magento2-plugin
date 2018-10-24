<?php

namespace AristanderAi\Aai\Block\PageView;

use AristanderAi\Aai\Service\PageRecorder;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Data extends Template
{
    /** @var PageRecorder */
    protected $pageRecord;

    public function __construct(
        Context $context,
        PageRecorder $pageRecord,
        array $data = []
    ) {
        $this->pageRecord = $pageRecord;

        parent::__construct($context, $data);
    }

    /**
     * @return PageRecorder
     */
    public function getPageRecord(): PageRecorder
    {
        return $this->pageRecord;
    }

    public function getTrackUrl()
    {
        return $this->getUrl('aristander-ai/track/page');
    }
}