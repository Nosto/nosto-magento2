<?php

namespace Nosto\Tagging\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\Template;
use Nosto\Model\Product\Product;
use Nosto\Model\Order\Order;

class MonitoringIndexer extends Template
{
    private static Product $nostoProduct;

    private static Order $nostoOrder;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormAction(string $url): string
    {
        return $this->getUrl($url, ['_secure' => true]);
    }

    public function setNostoProduct(Product $nostoProduct): void
    {
        self::$nostoProduct = $nostoProduct;
    }

    public function getNostoProduct(): Product
    {
        return self::$nostoProduct;
    }

    public function setNostoOrder(Order $nostoOrder): void
    {
        self::$nostoOrder = $nostoOrder;
    }

    public function getNostoOrder(): Order
    {
        return self::$nostoOrder;
    }
}