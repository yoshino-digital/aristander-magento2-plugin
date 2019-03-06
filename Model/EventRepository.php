<?php
namespace AristanderAi\Aai\Model;

use AristanderAi\Aai\Api\Data\EventInterface as ModelInterface;
use AristanderAi\Aai\Api\EventRepositoryInterface;
use AristanderAi\Aai\Model\ResourceModel\Event as EventResource;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class EventRepository
 * @package AristanderAi\Aai\Model
 */

class EventRepository implements EventRepositoryInterface
{
    /**
     * @var EventFactory
     */
    private $modelFactory;

    /** @var EventResource */
    private $resource;

    public function __construct(
        EventFactory $modelFactory,
        EventResource $resource

    ) {
        $this->modelFactory = $modelFactory;
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $result = $this->modelFactory->create();
        $this->resource->load($result, $id);

        if ($result->getId()) {
            throw new NoSuchEntityException(
                __("The event that was requested doesn't exist. Verify the event ID and try again.")
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
}
