<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;

class ProductMarginProvider extends ProductAttributeProvider
{
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;

    public function __construct(
        NostoHelperData $nostoDataHelper
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        $marginAttribute = $this->nostoDataHelper->getMarginAttribute($store);
        if ($product->hasData($marginAttribute)) {
            $nostoProduct->setSupplierCost($this->getAttributeValue($product, $marginAttribute));
        }
    }
}