<?php
namespace AristanderAi\Aai\Api;

use AristanderAi\Aai\Api\Data\EventInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface EventRepositoryInterface
{
    /**
     * Gets event by ID
     *
     * @param int $id
     * @return EventInterface
     * @throws NoSuchEntityException
     */
    public function get($id);

    /**
     * Saves event model
     *
     * @param EventInterface $model
     * @return $this
     */
    public function save(EventInterface $model);
}
