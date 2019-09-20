<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Model\CategoryString\Builder as NostoCategoryBuilder;

class ProductCategoryProvider implements ProductProvider
{

    /**
     * @var NostoCategoryBuilder
     */
    private $categoryBuilder;

    public function __construct(
        NostoCategoryBuilder $categoryBuilder
    ) {
        $this->categoryBuilder = $categoryBuilder;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        $nostoProduct->setCategories($this->categoryBuilder->buildCategories($product, $store));
    }
}