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
    private $objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    private $instanceName = null;

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
     * @return Event
     */
    public function create(array $data = [])
    {
        $instanceName = $this->instanceName;
        $type = null;
        if (isset($data['type'])) {
            $type = $data['type'];
            $instanceName .= '\\' . $this->typeToClass($type);

            unset($data['type']);
        }

        $result = $this->_create($instanceName, $data);
        $result->setType($type);

        return $result;
    }

    private function _create($instanceName, array $data = [])
    {
        return $this->objectManager->create($instanceName, $data);
    }

    /**
     * Ported uc_words function from Magento 1.x functions
     * Tiny function to enhance functionality of ucwords
     *
     * Will capitalize first letters and convert separators if needed
     *
     * @param string $type
     * @return string
     */
    private function typeToClass($type)
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $type))
        );
    }
}
