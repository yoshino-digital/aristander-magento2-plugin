<?php
namespace AristanderAi\Aai\Helper;

use AristanderAi\Aai\Model\Flag\AccessToken;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Flag\FlagResource;

class PushApi extends AbstractHelper
{
    /** @var Data */
    private $helperData;

    /** @var AccessToken */
    private $accessTokenFlag;

    /** @var FlagResource */
    private $flagResource;
    
    public function __construct(
        Context $context,
        Data $helperData, 
        AccessToken $accessTokenFlag,
        FlagResource $flagResource
    ) {
        $this->helperData = $helperData;
        $this->accessTokenFlag = $accessTokenFlag;
        $this->flagResource = $flagResource;

        parent::__construct($context);
    }

    /**
     * Reads access token from flags table and generates it if missing
     *
     * @return string
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAccessToken()
    {
        if (null === $this->accessTokenFlag->getId()) {
            $this->accessTokenFlag->loadSelf();
        }

        $result = $this->accessTokenFlag->getFlagData();
        if ('' == $result) {
            $result = bin2hex(openssl_random_pseudo_bytes(16));
            $this->accessTokenFlag->setFlagData($result);
            $this->flagResource->save($this->accessTokenFlag);
        }

        return $result;
    }

    /**
     * Returns site base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->_getUrl('');
    }

    /**
     * Returns API endpoint URL
     *
     * @param string $apiKey
     * @return string
     */
    public function getApiUrl($apiKey = 'prices')
    {
        return $this->_getUrl('aristander-ai/api/' . $apiKey);
    }
}
