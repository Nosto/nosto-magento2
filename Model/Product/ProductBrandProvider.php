<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;

class ProductBrandProvider extends ProductAttributeProvider
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
        $brandAttribute = $this->nostoDataHelper->getBrandAttribute($store);
        if ($product->hasData($brandAttribute)) {
            $nostoProduct->setBrand($this->getAttributeValue($product, $brandAttribute));
        }
    }
}