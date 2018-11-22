<?php
namespace AristanderAi\Aai\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    protected $configPath = 'aai';

    /** @var string Platform name for generating full version string */
    protected $platformName = 'magento-2';

    protected $moduleList;

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList
    ) {
        $this->moduleList = $moduleList;
        parent::__construct($context);
    }

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
            $this->configPath . '/' . $code,
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

    /**
     * Gets current module version
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->moduleList
            ->getOne($this->_getModuleName())['setup_version'];
    }

    /**
     * Gets full version string for supplying to events API
     *
     * @param string|null $moduleVersion
     * @return string
     */
    public function getVersionStamp($moduleVersion = null)
    {
        if ('' == $moduleVersion) {
            $moduleVersion = $this->getVersion();
        }

        return "{$this->platformName}-{$moduleVersion}";
    }

    /**
     * Gets module config path
     *
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }
}