<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\EventInterface;
use AristanderAi\Aai\Helper\Data;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Header;
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
    private $session;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var  \Magento\Store\Api\Data\StoreInterface */
    private $store;

    /** @var Data */
    private $helperData;

    /** @var ProductResource */
    private $productResource;

    /** @var Header */
    private $httpHeader;

    /**
     * Event constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Session $session
     * @param StoreManagerInterface $storeManager
     * @param Data $helperData
     * @param ProductResource $productResource
     * @param Header $httpHeader
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
        ProductResource $productResource,
        Header $httpHeader,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
        $this->productResource = $productResource;
        $this->httpHeader = $httpHeader;

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
    public function isEnabled()
    {
        return $this->hasType()
            ? $this->helperData->isEventTypeEnabled($this->getType())
            : $this->helperData->isEventTrackingEnabled();
    }

    /**
     * Collects basic event data
     *
     * @return $this
     */
    public function collect()
    {
        // Event metadata
        $key = 'timestamp';
        if (!$this->hasData($key)) {
            $this->setTimestamp();
        }
        $key = 'version';
        if (!$this->hasData($key)) {
            $this->setData($key, $this->helperData->getVersion());
        }

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
            $this->setData($key, $this->httpHeader->getHttpUserAgent());
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
        if (null === $this->store) {
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
            'timestamp',
            'version',
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
        // Export
        $result = $this->toArray($this->getExportAttributes());

        // Add version stamp
        if (isset($result['version']) && '' != $result['version']) {
            $result['plugin_version'] = $this->helperData->getVersionStamp(
                $result['version']
            );
            unset($result['version']);
        }

        // Convert product IDs to SKUs in details
        $details = &$result['details'];
        switch ($this->getType()) {
            case 'page':
            case 'order':
                foreach ($details['products'] as &$product) {
                    $product['product_id'] = $this->productIdToSku(
                        $product['product_id']
                    );
                }
                unset($product);

                break;

            case 'basket':
                $details['product_id'] = $this->productIdToSku(
                    $details['product_id']
                );

                break;
        }

        // Rename
        foreach ($this->getExportAttributeRenames() as $from => $to) {
            if (array_key_exists($from, $result)) {
                $result[$to] = $result[$from];
                unset($result[$from]);
            }
        }

        return $result;
    }

    //
    // Helper methods
    //

    /**
     * @param int $productId
     * @return string|null
     */
    private function productIdToSku($productId)
    {
        $result = $this->productResource->getAttributeRawValue(
            $productId,
            'sku',
            $this->getStoreId()
        );

        return isset($result['sku']) ? $result['sku'] : null;
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
    public function hasType()
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
     * @return string
     */
    public function getVersion()
    {
        return $this->_getData(self::VERSION);
    }

    /**
     * @param string $value
     * @return self
     */
    public function setVersion($value)
    {
        return $this->setData(self::VERSION, $value);
    }

    /**
     * @return int
     */
    public function getTimestamp()
    {
        return $this->_getData(self::TIMESTAMP);
    }

    /**
     * @param int|null $value
     * @return self
     */
    public function setTimestamp($value = null)
    {
        if (null === $value) {
            $value = time();
        }

        return $this->setData(self::TIMESTAMP, $value);
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
