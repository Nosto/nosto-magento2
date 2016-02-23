<?php

namespace Nosto\Tagging\Model;

use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Api\Data\CustomerInterface;

class Customer extends AbstractModel implements CustomerInterface
{
    /**
     * Name of cookie that holds Nosto visitor id
     */
    const COOKIE_NAME = '2c_cId';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Nosto\Tagging\Model\ResourceModel\Customer');
    }

    /**
     * @inheritdoc
     */
    public function getCustomerId()
    {
        return $this->getData(self::CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getNostoId()
    {
        return $this->getData(self::NOSTO_ID);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt()
    {
        return  $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritdoc
     */
    public function setNostoId($nostoId)
    {
        return $this->setData(self::NOSTO_ID, $nostoId);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
