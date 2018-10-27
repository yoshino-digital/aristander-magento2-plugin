<?php
namespace AristanderAi\Aai\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    protected $configPath = 'aai/';

    /**
     * Gets module config value
     *
     * @param string $code
     * @param int|null $storeId
     * @return string|null
     */
    public function getConfigValue($code, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $this->configPath . $code,
            ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /**
     * Indicates if the module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfigValue('general/enable');
    }

    /**
     * Indicates if event tracking is enabled for specific event type
     *
     * @param string|null $type event type or null for all event types
     * @return bool
     */
    public function isEventTypeEnabled($type): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!empty($type)
            && !$this->getConfigValue("event-{$type}/enable")
        ) {
            // Specific event type disabled
            return false;
        }

        return true;
    }
}