<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Product\Sku\Collection as NostoSkuCollection;

class ProductVariationProvider implements ProductProvider
{
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;
    /**
     * @var NostoSkuCollection
     */
    private $skuCollection;

    public function __construct(
        NostoHelperData $nostoDataHelper,
        NostoSkuCollection $skuCollection
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->skuCollection = $skuCollection;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        if ($this->nostoDataHelper->isVariationTaggingEnabled($store)) {
            $nostoProduct->setSkus($this->skuCollection->build($product, $store));
        }
    }
}