<?php

namespace Nosto\Tagging\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\Template;
use Nosto\Model\Product\Product;

class MonitoringProduct extends Template
{
    private static Product $nostoProduct;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setNostoProduct(Product $nostoProduct): void
    {
        self::$nostoProduct = $nostoProduct;
    }

    public function getNostoProduct(): Product
    {
        return self::$nostoProduct;
    }
}