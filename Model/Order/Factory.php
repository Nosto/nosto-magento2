<?php

namespace Nosto\Tagging\Model\Order;

use Magento\Framework\ObjectManagerInterface;

class Factory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * Create new order object.
     *
     * @param array $data
     * @return \NostoOrder
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create('NostoOrder', $data);
    }
}
