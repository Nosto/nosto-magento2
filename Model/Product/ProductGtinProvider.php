<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;

class ProductGtinProvider extends ProductAttributeProvider
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
        $gtinAttribute = $this->nostoDataHelper->getGtinAttribute($store);
        if ($product->hasData($gtinAttribute)) {
            $nostoProduct->setGtin($this->getAttributeValue($product, $gtinAttribute));
        }
    }
}