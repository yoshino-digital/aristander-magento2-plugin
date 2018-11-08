<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\EventInterface;
use AristanderAi\Aai\Helper\Data;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Event
 * @package AristanderAi\Aai\Model
 */

class Event extends AbstractModel implements EventInterface
{
    /** @var Session */
    protected $session;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var  \Magento\Store\Api\Data\StoreInterface */
    protected $store;

    /** @var Data */
    protected $helperData;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Session $session
     * @param StoreManagerInterface $storeManager
     * @param Data $helperData
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Session $session,
        StoreManagerInterface $storeManager,
        Data $helperData,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;

        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    protected function _construct()
    {
        $this->_init(ResourceModel\Event::class);
    }

    /**
     * Indicates if event tracking is enabled for this event type
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->hasType()
            ? $this->helperData->isEventTypeEnabled($this->getType())
            : $this->helperData->isEnabled();
    }

    /**
     * Collects basic event data
     *
     * @return $this
     */
    public function collectGeneralProperties()
    {
        // Session data
        $key = 'session_id';
        if (!$this->hasData($key)) {
            $this->setData($key, $this->session->getSessionId());
        }
        $key = 'user_id';
        if (!$this->hasData($key)) {
            $this->setData($key, $this->session->getData('customer_id'));
        }
        $key = 'user_agent';
        if (!$this->hasData($key)) {
            $this->setData($key, $_SERVER['HTTP_USER_AGENT']);
        }

        // Store data
        $key = 'website_id';
        if (!$this->hasData($key) && $this->getStore()) {
            $this->setData($key, $this->getStore()->getWebsiteId());
        }
        $key = 'store_group_id';
        if (!$this->hasData($key) && $this->getStore()) {
            $this->setData($key, $this->getStore()->getStoreGroupId());
        }
        $key = 'store_id';
        if (!$this->hasData($key) && $this->getStore()) {
            $this->setData($key, $this->getStore()->getId());
        }

        return $this;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    public function getStore()
    {
        if (is_null($this->store)) {
            try {
                $this->store = $this->storeManager->getStore();
            } catch (NoSuchEntityException $e) {
                $this->store = false;
            }
        }

        return $this->store ?: null;
    }

    /**
     * Specifies attributes for export to API
     *
     * @return array
     */
    public function getExportAttributes()
    {
        return [
            'type',
            'session_id',
            'user_agent',
            'user_id',
            'session_id',
            'store_id',
            'store_group_id',
            'website_id',
            'details',
        ];
    }

    /**
     * Specifies attributes to rename when exporting to API
     *
     * @return array
     */
    public function getExportAttributeRenames()
    {
        return [
            'type' => 'event_type',
            'details' => 'event_details',
        ];
    }

    /**
     * Exports data for API
     *
     * @return array
     */
    public function export()
    {
        $exportAttributes = $this->getExportAttributes();

        $result = $this->toArray($exportAttributes);
        foreach ($this->getExportAttributeRenames() as $from => $to) {
            if (array_key_exists($from, $result)) {
                $result[$to] = $result[$from];
                unset($result[$from]);
            }
        }

        return $result;
    }


    //
    // Getters and setters
    //

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->_getData(self::TYPE);
    }

    /**
     * @param string $value
     * @return self
     */
    public function setType($value)
    {
        return $this->setData(self::TYPE, $value);
    }

    /**
     * @return bool
     */
    public function hasType(): bool
    {
        return $this->hasData(self::TYPE);
    }

    /**
     * @return string|null;
     */
    public function getStatus()
    {
        return $this->_getData(self::STATUS);
    }

    /**
     * @param string $value
     * @return mixed
     */
    public function setStatus($value)
    {
        return $this->setData(self::STATUS, $value);
    }

    /**
     * @return string|null;
     */
    public function getSessionId()
    {
        return $this->_getData(self::SESSION_ID);
    }

    /**
     * @param string|null $value
     * @return self
     */
    public function setSessionId($value)
    {
        return $this->setData(self::SESSION_ID, $value);
    }

    /**
     * @return string|null;
     */
    public function getUserAgent()
    {
        return $this->_getData(self::USER_AGENT);
    }

    /**
     * @param string|null $value
     * @return self
     */
    public function setUserAgent($value)
    {
        return $this->setData(self::USER_AGENT, $value);
    }

    /**
     * @return int|null;
     */
    public function getUserId()
    {
        return $this->_getData(self::USER_ID);
    }

    /**
     * @param int|null $value
     * @return self
     */
    public function setUserId($value)
    {
        return $this->setData(self::USER_ID, $value);
    }

    /**
     * @return int|null;
     */
    public function getStoreId()
    {
        return $this->_getData(self::STORE_ID);
    }

    /**
     * @param int|null $value
     * @return self
     */
    public function setStoreId($value)
    {
        return $this->setData(self::STORE_ID, $value);
    }

    /**
     * @return int|null;
     */
    public function getStoreGroupId()
    {
        return $this->_getData(self::STORE_GROUP_ID);
    }

    /**
     * @param int|null $value
     * @return self
     */
    public function setStoreGroupId($value)
    {
        return $this->setData(self::STORE_GROUP_ID, $value);
    }

    /**
     * @return int|null;
     */
    public function getWebsiteId()
    {
        return $this->_getData(self::WEBSITE_ID);
    }

    /**
     * @param int|null $value
     * @return self
     */
    public function setWebsiteId($value)
    {
        return $this->setData(self::WEBSITE_ID, $value);
    }

    /**
     * @return array;
     */
    public function getDetails()
    {
        return $this->_getData(self::DETAILS);
    }

    /**
     * @param array $value
     * @return self
     */
    public function setDetails($value)
    {
        return $this->setData(self::DETAILS, $value);
    }

    /**
     * @return string|null;
     */
    public function getCreatedAt()
    {
        return $this->_getData(self::CREATED_AT);
    }

    /**
     * @return string|null;
     */
    public function getSyncedAt()
    {
        return $this->_getData(self::SYNCED_AT);
    }

    /**
     * @param string|null $value
     * @return self
     */
    public function setSyncedAt($value)
    {
        return $this->setData(self::SYNCED_AT, $value);
    }

    /**
     * @return string|null;
     */
    public function getLastError()
    {
        return $this->_getData(self::LAST_ERROR);
    }

    /**
     * @param string|null $value
     * @return self
     */
    public function setLastError($value)
    {
        return $this->setData(self::LAST_ERROR, $value);
    }
}