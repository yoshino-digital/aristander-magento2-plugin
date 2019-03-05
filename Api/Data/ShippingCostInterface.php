<?php
namespace AristanderAi\Aai\Api\Data;

interface ShippingCostInterface
{
    const ID = 'id';
    const QUOTE_ID = 'quote_id';
    const CODE = 'code';
    const COST = 'cost';

    /**
     * @return int
     */
    public function getId();

    /**
     * @return int
     */
    public function getQuoteId();

    /**
     * @param int $value
     * @return $this
     */
    public function setQuoteId($value);

    /**
     * @return string;
     */
    public function getCode();

    /**
     * @param string $value
     * @return $this
     */
    public function setCode($value);

    /**
     * @return float;
     */
    public function getCost();

    /**
     * @param float $value
     * @return $this
     */
    public function setCost($value);
}
