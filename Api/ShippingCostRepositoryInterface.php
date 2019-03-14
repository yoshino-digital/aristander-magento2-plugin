<?php
namespace AristanderAi\Aai\Api;

use AristanderAi\Aai\Api\Data\ShippingCostInterface;

interface ShippingCostRepositoryInterface
{
    /**
     * Gets model by primary key
     *
     * @param int $id
     * @return ShippingCostInterface|null
     */
    public function get($id);

    /**
     * Gets model by natural key
     *
     * @param int $quoteId
     * @param string $code
     * @return ShippingCostInterface|null
     */
    public function getByNaturalKey($quoteId, $code);

    /**
     * Saves model
     *
     * @param ShippingCostInterface $model
     * @return $this
     */
    public function save(ShippingCostInterface $model);

    /**
     * @param int $quoteId
     * @return $this
     */
    public function deleteForQuote($quoteId);

    /**
     * @param int $quoteId
     * @param array $values
     * @return $this
     */
    public function saveForQuote($quoteId, array $values);
}
