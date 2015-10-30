<?php

namespace Nosto\Tagging\Model\Cart\Item;

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
     * Create new cart item object.
     *
     * @param array $data
     * @return \NostoCartItem
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create('NostoCartItem', $data);
    }
}
