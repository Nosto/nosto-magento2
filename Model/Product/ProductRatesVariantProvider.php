<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;

class ProductRatesVariantProvider implements ProductProvider
{

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        // TODO: Implement addData() method.
    }
}