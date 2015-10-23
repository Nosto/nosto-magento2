<?php

namespace Nosto\Tagging\Model\Category;

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
    public function __construct(ObjectManagerInterface $objectManager) {
        $this->_objectManager = $objectManager;
    }

    /**
     * Create new product object.
     *
     * @param array $data
     * @return \Nosto\Tagging\Model\Category
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create(
            'Nosto\Tagging\Model\Category',
            $data
        );
    }
}
