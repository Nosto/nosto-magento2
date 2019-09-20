<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Ratings as NostoRating;

class ProductRatingProvider implements ProductProvider
{

    /**
     * @var NostoRating
     */
    private $nostoRatingHelper;

    public function __construct(
        NostoRating $nostoRatingHelper
    ) {
        $this->nostoRatingHelper = $nostoRatingHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        $rating = $this->nostoRatingHelper->getRatings($product, $store);
        if ($rating !== null) {
            $nostoProduct->setRatingValue($rating->getRating());
            $nostoProduct->setReviewCount($rating->getReviewCount());
        }
    }
}