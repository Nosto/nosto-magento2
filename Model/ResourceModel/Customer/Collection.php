<?php

namespace Nosto\Tagging\Model\ResourceModel\Customer;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'Nosto\Tagging\Model\Customer',
            'Nosto\Tagging\Model\ResourceModel\Customer'
        );
    }
}
