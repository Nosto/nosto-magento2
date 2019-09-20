<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Model\Product\Url\Builder as NostoUrlBuilder;

class ProductFieldsProvider implements ProductProvider
{

    /**
     * @var NostoUrlBuilder
     */
    private $urlBuilder;

    public function __construct(
        NostoUrlBuilder $urlBuilder
    ) {
        $this->urlBuilder = $urlBuilder;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        $nostoProduct->setCustomFields($this->getCustomFieldsWithAttributes($product, $store));
    }
}