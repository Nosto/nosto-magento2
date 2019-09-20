<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Stock as NostoStockHelper;

class ProductInventoryProvider implements ProductProvider
{
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;
    /**
     * @var NostoStockHelper
     */
    private $nostoStockHelper;

    public function __construct(
        NostoHelperData $nostoDataHelper,
        NostoStockHelper $nostoStockHelper
    ) {
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoStockHelper = $nostoStockHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        if ($this->nostoDataHelper->isInventoryTaggingEnabled($store)) {
            $nostoProduct->setInventoryLevel($this->nostoStockHelper->getQty($product));
        }
    }
}