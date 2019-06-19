<?php
namespace Nosto\Tagging\Test\_util;

use Magento\Catalog\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\ObjectManagerInterface;

final class OrderBuilder implements BuilderInterface
{
    const DEFAULT_ORDER_INCREMENT_ID = '000000001';

    /* @var Order */
    private $order;

    /* @var ObjectManagerInterface */
    private $objectManager;

    /**
     * OrderBuilder constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->order = $objectManager->create(Order::class);
    }

    /**
     * @return $this
     */
    public function defaultOrder()
    {
        $this->order->loadByIncrementId(self::DEFAULT_ORDER_INCREMENT_ID);
        return $this;
    }

    /**
     * @return Order
     */
    public function build()
    {
        return $this->order;
    }
}
