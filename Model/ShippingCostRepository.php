<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\ShippingCostInterface as ModelInterface;
use AristanderAi\Aai\Api\ShippingCostRepositoryInterface;
use AristanderAi\Aai\Model\ResourceModel\ShippingCost as Resource;
use AristanderAi\Aai\Model\ResourceModel\ShippingCost\CollectionFactory;
use AristanderAi\Aai\Model\ShippingCost as Model;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class EventRepository
 * @package AristanderAi\Aai\Model
 */

class ShippingCostRepository implements ShippingCostRepositoryInterface
{
    /**
     * @var ShippingCostFactory
     */
    private $modelFactory;

    /** @var Resource */
    private $resource;

    /** @var CollectionFactory */
    private $collectionFactory;


    public function __construct(
        ShippingCostFactory $modelFactory,
        Resource $resource,
        CollectionFactory $collectionFactory

    ) {
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $result = $this->modelFactory->create();
        $this->resource->load($result, $id);

        if ($result->getId()) {
            throw new NoSuchEntityException(
                __("The record that was requested doesn't exist. Verify the record ID and try again.")
            );
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(ModelInterface $model)
    {
        /** @noinspection PhpParamsInspection */
        $this->resource->save($model);

        return $this;
    }

    /**
     * Gets model by natural key
     *
     * @param int $quoteId
     * @param string $code
     * @return ModelInterface
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($quoteId, $code)
    {
        $id = $this->resource->getIdByNaturalKey($quoteId, $code);
        if (!$id) {
            throw new NoSuchEntityException(
                __("The shipping cost for this quote ID and code doesn't exist.")
            );
        }

        return $this->getById($id);
    }

    /**
     * @param int $quoteId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteQuoteCosts($quoteId)
    {
        $this->resource->deleteByQuote($quoteId);

        return $this;
    }

    /**
     * @param int $quoteId
     * @param array $values
     * @return $this
     * @throws \Exception
     */
    public function saveQuoteCosts($quoteId, array $values)
    {
        /** @var Resource\Collection $collection */
        $collection = $this->collectionFactory->create();

        foreach ($values as $code => $cost) {
            /** @var Model $item */
            $item = $this->modelFactory->create();
            $item->setQuoteId($quoteId);
            $item->setCode($code);
            $item->setCost($cost);

            $collection->addItem($item);
        }

        $connection = $this->resource->getConnection();

        $connection->beginTransaction();

        try {
            $this->deleteQuoteCosts($quoteId);
            $collection->walk([$this, 'save']);
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $connection->commit();

        return $this;
    }
}
