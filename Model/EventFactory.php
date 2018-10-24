<?php
namespace AristanderAi\Aai\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory class for @see \AristanderAi\Aai\Model\Event
 */
class EventFactory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected $instanceName = null;

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        $instanceName = '\\AristanderAi\\Aai\\Model\\Event'
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return \AristanderAi\Aai\Model\Event
     */
    public function create(array $data = [])
    {
        $instanceName = $this->instanceName;
        $type = null;
        if (isset($data['type'])) {
            $type = $data['type'];
            $instanceName .= '\\' . ucfirst($data['type']);

            unset($data['type']);
        }


        $result = $this->_create($instanceName, $data);
        $result->setType($type);

        return $result;
    }

    protected function _create($instanceName, array $data = [])
    {
        return $this->objectManager->create($instanceName, $data);
    }
}
