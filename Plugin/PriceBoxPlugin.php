<?php
namespace AristanderAi\Aai\Plugin;

use Magento\Framework\Pricing\Render\PriceBox;
use AristanderAi\Aai\Service\EventRecorder\Page as PageRecorder;

class PriceBoxPlugin
{
    /** @var PageRecorder */
    private $pageRecorder;

    public function __construct(PageRecorder $pageRecorder)
    {
        $this->pageRecorder = $pageRecorder;
    }

    /**
     * @param PriceBox $subject
     * @param string $result
     * @return string
     */
    public function afterRenderAmount(PriceBox $subject, $result)
    {
        if (!$this->pageRecorder->isStarted()) {
            return $result;
        }

        $injectHtml = $this->pageRecorder->recordProduct($subject->getSaleableItem());
        if (!empty($injectHtml)) {
            $result .= "\n" . $injectHtml;
        }

        return $result;
    }
}
