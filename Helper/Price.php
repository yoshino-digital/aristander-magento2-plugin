<?php
namespace AristanderAi\Aai\Helper;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;

class Price extends AbstractHelper
{
    private $cookieName = 'aai_testing';

    private $cookieLifetime = 86400;

    private $customerGroupCode = 'Aristander Price';

    /** @var string The attribute duplicates customer group price for
     * performance optimized access
     */
    private $alternativePriceAttributeCode = 'aai_alternative_price';
    
    private $customerGroupTaxClassId = 3; // Retail customer

    /** @var int|null */
    private $customerGroupId;

    /** @var bool|null */
    private $alternativePriceFlag;

    /** @var Data */
    private $helperData;

    /** @var \Magento\Framework\Stdlib\CookieManagerInterface */
    private $cookie;

    /** @var GroupInterfaceFactory  */
    private $groupFactory;

    /** @var GroupRepositoryInterface  */
    private $groupRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var FilterBuilder  */
    private $filterBuilder;

    /** @var ProductResource */
    private $productResource;

    public function __construct(
        Context $context,
        Data $helperData,
        CookieManagerInterface $cookie,
        GroupInterfaceFactory $groupFactory,
        GroupRepositoryInterface $groupRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        ProductResource $productResource
    ) {
        $this->helperData = $helperData;
        $this->cookie = $cookie;
        $this->groupFactory = $groupFactory;
        $this->groupRepository = $groupRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->productResource = $productResource;

        parent::__construct($context);
    }

    /**
     * @param Product $product
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function initProductPrice(Product $product)
    {
        if (!$this->getAlternativePriceFlag()) {
            return $this;
        }

        if (!$product->hasData($this->alternativePriceAttributeCode)) {
            return $this;
        }

        $price = $product->getData($this->alternativePriceAttributeCode);
        if (null === $price) {
            return $this;
        }

        $product->setData('final_price', $price);
        $product->setData('price', $price);
        $product->unsetData('special_price');

        return $this;
    }

    /**
     * @param Session $session
     * @return $this
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function initCustomerSession(Session $session)
    {
        if (!$this->getAlternativePriceFlag()) {
            if ($this->getCustomerGroupId() == $session->getData('customer_group_id')) {
                $session->setCustomerGroupId(null);
            }

            return $this;
        }

        $session->setCustomerGroupId($this->getCustomerGroupId());

        return $this;
    }

    /**
     * Gets active price mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->helperData->getConfigValue('price_import/price_mode');
    }

    /**
     * Sets active price mode
     *
     * @param $value
     * @return $this
     */
    public function setMode($value)
    {
        if ($value == $this->getMode()) {
            return $this;
        }

        $this->helperData->setConfigValue(
            'price_import/price_mode',
            $value
        );

        return $this;
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function getAlternativePriceFlag()
    {
        if (null === $this->alternativePriceFlag) {
            $this->initAlternativePriceFlag();
        }

        return $this->alternativePriceFlag;
    }

    /**
     * @return \Magento\Customer\Api\Data\GroupInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function initCustomerGroup()
    {
        $generalFilter[] = $this->filterBuilder
            ->setField('customer_group_code')
            ->setConditionType('eq')
            ->setValue($this->customerGroupCode)
            ->create();
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters($generalFilter)
            ->create();

        $list = $this->groupRepository->getList($searchCriteria);
        if ($list->getTotalCount() > 0) {
            $result = $list->getItems()[0];
        } else {
            $result = $this->groupFactory->create();
            $result->setCode($this->customerGroupCode);
            $result->setTaxClassId($this->customerGroupTaxClassId);

            $this->groupRepository->save($result);
        }

        return $result;
    }

    //
    // Product price management
    //

    /**
     * Finds an index of group price used to apply alternative price
     *
     * @param Product $product
     * @return int|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductAlternativePriceKey($product)
    {
        $prices = $product->getData('tier_price');

        if ($prices === null) {
            if ($attribute = $this->productResource->getAttribute('tier_price')) {
                $attribute->getBackend()->afterLoad($product);
                $prices = $product->getData('tier_price');
            }
        }

        foreach ($prices as $key => $price) {
            if (0 == $price['website_id']
                && $this->getCustomerGroupId() == $price['cust_group']
            ) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Gets alternative price for a product
     *
     * @param Product $product
     * @return float|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductAlternativePrice(Product $product)
    {
        if ($product->hasData($this->alternativePriceAttributeCode)) {
            return $product->getData($this->alternativePriceAttributeCode);
        }

        $key = $this->getProductAlternativePriceKey($product);
        if (null === $key) {
            return null;
        }

        $prices = $product->getData('tier_price');
        return $prices[$key]['price'];
    }

    /**
     * Sets alternative price for a product
     *
     * @param Product $product
     * @param $value
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setProductAlternativePrice(Product $product, $value) 
    {
        if (null === $value) {
            return $this->unsetProductAlternativePrice($product);
        }

        $key = $this->getProductAlternativePriceKey($product);
        $prices = $product->getData('tier_price');
        if (null !== $key) {
            $prices[$key]['price'] = $value;
            $prices[$key]['percentage_value'] = null;
            $prices[$key]['all_groups'] = 0;
            $prices[$key]['price_qty'] = 1;
            unset($prices[$key]['website_price']);
        } else {
            $prices[] = [
                'website_id' => 0,
                'cust_group' => $this->getCustomerGroupId(),
                'price' => $value,
                'price_qty' => 1,
            ];
        }

        $product->setData('tier_price', $prices);
        $product->setData('force_reindex_required', true);

        return $this;
    }

    /**
     * Removes alternative price for a product
     *
     * @param Product $product
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function unsetProductAlternativePrice(Product $product)
    {
        $key = $this->getProductAlternativePriceKey($product);
        if (null === $key) {
            return $this;
        }

        $prices = $product->getData('tier_price');
        $prices[$key]['delete'] = true;

        $product->setData('tier_price', $prices);
        $product->setData('force_reindex_required', true);

        return $this;
    }

    /**
     * Updates aai_alternative_price attribute to reflect Aristander Price
     * customer group price
     *
     * @param Product $product
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handleProductAlternativePriceUpdate(Product $product)
    {
        $product->unsetData($this->alternativePriceAttributeCode);
        $price = $this->getProductAlternativePrice($product);
        if (null !== $price) {
            $product->setData($this->alternativePriceAttributeCode, $price);
        } else {
            $product->setData($this->alternativePriceAttributeCode, null);
        }

        return $this;
    }

    //
    // Getters and setters
    //

    /**
     * @return string
     */
    public function getCustomerGroupCode()
    {
        return $this->customerGroupCode;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function getCustomerGroupId()
    {
        if (null === $this->customerGroupId) {
            $group = $this->initCustomerGroup();
            $this->customerGroupId = $group->getId();
        }

        return $this->customerGroupId;
    }

    /**
     * @return string
     */
    public function getAlternativePriceAttributeCode()
    {
        return $this->alternativePriceAttributeCode;
    }

    //
    // Private methods
    //

    /**
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    private function initAlternativePriceFlag()
    {
        switch ($this->getMode()) {
            case 'alternative':
                $this->alternativePriceFlag = true;
                break;

            case 'split':
                $crawlerDetect = new CrawlerDetect();
                if ($crawlerDetect->isCrawler()) {
                    $this->alternativePriceFlag = false;
                    break;
                }

                $version = $this->cookie->getCookie($this->cookieName);
                if ('' == $version) {
                    // Cookie not set - randomize and assign
                    $version = rand(0, 1);
                }

                $cookiePath = parse_url(
                    $this->_getUrl(''),
                    PHP_URL_PATH
                );
                if ('' == $cookiePath) {
                    $cookiePath = '/';
                }

                $this->cookie->setPublicCookie(
                    $this->cookieName,
                    $version,
                    new PublicCookieMetadata([
                        PublicCookieMetadata::KEY_DURATION => $this->cookieLifetime,
                        PublicCookieMetadata::KEY_PATH => $cookiePath,
                    ])
                );

                $this->alternativePriceFlag = 1 == $version;

                break;

            case 'original':
            default:
                $this->alternativePriceFlag = false;
                break;
        }
    }
}
