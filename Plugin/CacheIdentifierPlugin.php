<?php
namespace AristanderAi\Aai\Plugin;

use AristanderAi\Aai\Helper\Price;
use Magento\Framework\App\PageCache\Identifier;

class CacheIdentifierPlugin
{
    /** @var Price */
    private $helperPrice;

    public function __construct(
        Price $helperPrice
    ) {
        $this->helperPrice = $helperPrice;
    }

    /**
     * @param Identifier $subject
     * @param string $result
     * @return string
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function afterGetValue(Identifier $subject, $result)
    {
        if ($this->helperPrice->getAlternativePriceFlag()) {
            $result .= '-alt_price';        }

        return $result;
    }
}
