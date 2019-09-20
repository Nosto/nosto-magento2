<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;

interface ProductProvider
{
    function addData(Product $product, Store $store, NostoProduct $nostoProduct);
}