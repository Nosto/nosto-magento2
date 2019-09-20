<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Product\Tags\LowStock as LowStockHelper;
use Nosto\Types\Product\ProductInterface;

class ProductTagsProvider implements ProductProvider
{

    /**
     * @var LowStockHelper
     */
    private $lowStockHelper;
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;

    public function __construct(
        NostoHelperData $nostoDataHelper,
        LowStockHelper $lowStockHelper
    ) {
        $this->lowStockHelper = $lowStockHelper;
        $this->nostoDataHelper = $nostoDataHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        if (($tags = $this->buildTags($product, $store)) !== []) {
            $nostoProduct->setTag1($tags);
        }
    }

    /**
     * @param MagentoProduct $product
     * @param Store $store
     * @return array
     */
    public function buildTags(MagentoProduct $product, Store $store)
    {
        $tags = [];

        if (!$product->canConfigure()) {
            $tags[] = ProductInterface::ADD_TO_CART;
        }

        if ($this->nostoDataHelper->isLowStockIndicationEnabled($store)
            && $this->lowStockHelper->build($product)
        ) {
            $tags[] = ProductInterface::LOW_STOCK;
        }

        return $tags;
    }
}