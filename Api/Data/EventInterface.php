<?php
namespace AristanderAi\Aai\Api\Data;

interface EventInterface
{
    const TYPE = 'type';
    const STATUS = 'status';
    const SESSION_ID = 'session_id';
    const USER_AGENT = 'user_agent';
    const USER_ID = 'user_id';
    const STORE_ID = 'store_id';
    const STORE_GROUP_ID = 'store_group_id';
    const WEBSITE_ID = 'website_id';
    const DETAILS = 'details';
    const CREATED_AT = 'created_at';
    const SYNCED_AT = 'synced_at';
    const VERSION = 'version';
    const PRICE_MODE = 'price_mode';
    const PRICELIST_SOURCE = 'pricelist_source';
    const MODEL_PARAMS = 'model_params';
    const TIMESTAMP = 'timestamp';
    const LAST_ERROR = 'last_error';

    /**
     * @return string|null
     */
    public function getType();

    /**
     * @param string $value
     * @return $this
     */
    public function setType($value);

    /**
     * @return bool
     */
    public function hasType();

    /**
     * @return string|null;
     */
    public function getStatus();

    /**
     * @param string $value
     * @return mixed
     */
    public function setStatus($value);

    /**
     * @return string|null;
     */
    public function getSessionId();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setSessionId($value);

    /**
     * @return string|null;
     */
    public function getUserAgent();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setUserAgent($value);

    /**
     * @return int|null;
     */
    public function getUserId();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setUserId($value);

    /**
     * @return int|null;
     */
    public function getStoreId();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setStoreId($value);

    /**
     * @return int|null;
     */
    public function getStoreGroupId();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setStoreGroupId($value);

    /**
     * @return int|null;
     */
    public function getWebsiteId();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setWebsiteId($value);

    /**
     * @return array;
     */
    public function getDetails();

    /**
     * @param array $value
     * @return $this
     */
    public function setDetails($value);

    /**
     * @return string
     */
    public function getVersion();

    /**
     * @param string $value
     * @return $this
     */
    public function setVersion($value);

    /**
     * @return string
     */
    public function getPriceMode();

    /**
     * @param string $value
     * @return $this
     */
    public function setPriceMode($value);

    /**
     * @return string
     */
    public function getPricelistSource();

    /**
     * @param string $value
     * @return $this
     */
    public function setPricelistSource($value);

    /**
     * @return string|null
     */
    public function getModelParams();

    /**
     * @param string $value|null
     * @return $this
     */
    public function setModelParams($value);

    /**
     * @return float
     */
    public function getTimestamp();

    /**
     * @param float|null $value
     * @return $this
     */
    public function setTimestamp($value = null);

    /**
     * @return string|null;
     */
    public function getCreatedAt();

    /**
     * @return string|null;
     */
    public function getSyncedAt();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setSyncedAt($value);

    /**
     * @return string|null;
     */
    public function getLastError();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setLastError($value);
}
