<?php
namespace AristanderAi\Aai\Api;

use AristanderAi\Aai\Api\Data\EventInterface;

interface EventRepositoryInterface
{
    /**
     * Gets event by ID
     *
     * @param int $id
     * @return EventInterface|null
     */
    public function get($id);

    /**
     * Saves event model
     *
     * @param EventInterface $event
     * @return self
     */
    public function save(EventInterface $event);
}
