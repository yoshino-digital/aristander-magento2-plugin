<?php
namespace AristanderAi\Aai\Plugin;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\PollApi\SendEvents;
use Magento\Config\Model\Config;

class ConfigPlugin
{
    /** @var Data */
    private $helperData;

    /** @var SendEvents */
    private $sendEvents;

    public function __construct(
        Data $helperData,
        SendEvents $sendEvents
    ) {
        $this->helperData = $helperData;
        $this->sendEvents = $sendEvents;
    }

    public function aroundSave(Config $subject, callable $proceed)
    {
        if ($this->helperData->getConfigPath() != $subject->getData('section')) {
            // Not our section
            return $proceed();
        }

        if ('' != $subject->getConfigDataValue('aai/general/api_key')) {
            // Already configured
            return $proceed();
        }

        $returnValue = $proceed();

        if ('' != $subject->getConfigDataValue('aai/general/api_key')) {
            // Change to non-empty
            $this->sendEvents->ping();
        }

        return $returnValue;
    }
}
