<?php

namespace AristanderAi\Aai\Block\PageView;

use AristanderAi\Aai\Service\PageRecorder;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Data extends Template
{
    /** @var PageRecorder */
    protected $pageRecorder;

    public function __construct(
        Context $context,
        PageRecorder $pageRecorder,
        array $data = []
    ) {
        $this->pageRecorder = $pageRecorder;

        parent::__construct($context, $data);
    }

    /**
     * @return PageRecorder
     */
    public function getPageRecorder(): PageRecorder
    {
        return $this->pageRecorder;
    }

    public function getTrackUrl()
    {
        return $this->getUrl('aristander-ai/track/page');
    }
}