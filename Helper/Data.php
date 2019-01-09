<?php
namespace AristanderAi\Aai\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private $configPath = 'aai';

    /** @var string Platform name for generating full version string */
    private $platformName = 'magento-2';

    /** @var ModuleListInterface */
    private $moduleList;
    
    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    /** @var \Magento\Framework\App\Config\Storage\WriterInterface */
    private $configWriter;

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->moduleList = $moduleList;
        $this->priceCurrency = $priceCurrency;
        $this->configWriter = $configWriter;
        
        parent::__construct($context);
    }

    /**
     * Gets module config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return string|null
     */
    public function getConfigValue($path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $this->configPath . '/' . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Sets module config value
     *
     * @param string $path
     * @param string|null $value
     * @return self
     */
    public function setConfigValue($path, $value)
    {
        $this->configWriter->save($path, $value);
        //TODO: check if cache clean is needed here

        return $this;
    }

    /**
     * Indicates if event tracking subsystem is enabled
     *
     * @return bool
     */
    public function isEventTrackingEnabled()
    {
        return $this->isModuleEnabled()
            && $this->getConfigValue('event_tracking/enabled');
    }

    /**
     * Indicates if collection of specific event type is enabled
     * using reserved settings
     *
     * @param string|null $type
     * @return bool
     */
    public function isEventTypeEnabled($type)
    {
        if (!$this->isEventTrackingEnabled()) {
            return false;
        }

        if (!empty($type)
            && !$this->getConfigValue("event_tracking/{$type}_enabled")
        ) {
            // Specific event type disabled
            return false;
        }

        return true;
    }

    /**
     * Indicates if price import is enabled
     *
     * @return bool
     */
    public function isPriceImportEnabled()
    {
        return $this->isModuleEnabled()
            && $this->getConfigValue('price_import/enabled');
    }

    /**
     * Sets price import enabled flag
     *
     * @param $value bool
     * @return self;
     */
    public function setPriceImportEnabled($value)
    {
        $this->setConfigValue('price_import/enabled', $value? 1 : 0);

        return $this;
    }

    /**
     * Stub added for compatibility with Magento 1.x code
     * Could be implemented later
     *
     * @return true
     */
    public function isModuleEnabled()
    {
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

    /**
     * @param $value
     * @return string|null
     */
    public function formatPrice($value)
    {
        if (null === $value) {
            return null;
        }

        return (string) $this->priceCurrency->round($value);
    }
}
