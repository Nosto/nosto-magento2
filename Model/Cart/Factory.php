<?php

namespace Nosto\Tagging\Model\Cart;

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
     * Create new cart object.
     *
     * @param array $data
     * @return \NostoCart
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create('NostoCart', $data);
    }
}
