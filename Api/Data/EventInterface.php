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
    const LAST_ERROR = 'last_error';

    /**
     * @return string|null
     */
    public function getType();

    /**
     * @param string $value
     * @return self
     */
    public function setType($value);

    /**
     * @return bool
     */
    public function hasType(): bool;

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
     * @return self
     */
    public function setSessionId($value);

    /**
     * @return string|null;
     */
    public function getUserAgent();

    /**
     * @param string|null $value
     * @return self
     */
    public function setUserAgent($value);

    /**
     * @return int|null;
     */
    public function getUserId();

    /**
     * @param int|null $value
     * @return self
     */
    public function setUserId($value);

    /**
     * @return int|null;
     */
    public function getStoreId();

    /**
     * @param int|null $value
     * @return self
     */
    public function setStoreId($value);

    /**
     * @return int|null;
     */
    public function getStoreGroupId();

    /**
     * @param int|null $value
     * @return self
     */
    public function setStoreGroupId($value);

    /**
     * @return int|null;
     */
    public function getWebsiteId();

    /**
     * @param int|null $value
     * @return self
     */
    public function setWebsiteId($value);

    /**
     * @return array;
     */
    public function getDetails();

    /**
     * @param array $value
     * @return self
     */
    public function setDetails($value);

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
     * @return self
     */
    public function setSyncedAt($value);

    /**
     * @return string|null;
     */
    public function getLastError();

    /**
     * @param string|null $value
     * @return self
     */
    public function setLastError($value);
}