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

    /**
     * @param Config $subject
     * @param callable $proceed
     * @return Config
     */
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

        $result = $proceed();

        if ('' != $subject->getConfigDataValue('aai/general/api_key')) {
            // Change to non-empty
            $this->sendEvents->ping();
        }

        return $result;
    }
}
