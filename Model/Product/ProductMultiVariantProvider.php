<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Model\Product\Variation\Collection as PriceVariationCollection;

class ProductMultiVariantProvider implements ProductProvider
{

    /**
     * @var PriceVariationCollection
     */
    private $priceVariationCollection;

    public function __construct(
        PriceVariationCollection $priceVariationCollection
    )
    {
        $this->priceVariationCollection = $priceVariationCollection;
    }

    function addData(\Magento\Catalog\Model\Product $product, Store $store, NostoProduct $nostoProduct)
    {
        $nostoProduct->setVariations(
            $this->priceVariationCollection->build($product, $nostoProduct, $store)
        );
    }
}