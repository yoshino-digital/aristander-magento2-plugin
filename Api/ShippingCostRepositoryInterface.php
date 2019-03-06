<?php
namespace AristanderAi\Aai\Api;

use AristanderAi\Aai\Api\Data\ShippingCostInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface ShippingCostRepositoryInterface
{
    /**
     * Gets model by primary key
     *
     * @param int $id
     * @return ShippingCostInterface
     * @throws NoSuchEntityException
     */
    public function getById($id);

    /**
     * Gets model by natural key
     *
     * @param int $quoteId
     * @param string $code
     * @return ShippingCostInterface
     * @throws NoSuchEntityException
     */
    public function get($quoteId, $code);

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
    public function deleteQuoteCosts($quoteId);

    /**
     * @param int $quoteId
     * @param array $values
     * @return $this
     */
    public function saveQuoteCosts($quoteId, array $values);
}
