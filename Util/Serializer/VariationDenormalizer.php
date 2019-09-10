<?php
namespace Nosto\Tagging\Util\Serializer;

use Nosto\Object\Product\Variation;

class VariationDenormalizer
{
    /**
     * @param array $variationData
     * @return Variation
     */
    public static function build(array $variationData)
    {
        $variation = new Variation();
        $variation->setVariationId($variationData['variationId']);
        $variation->setPrice($variationData['price']);
        $variation->setListPrice($variationData['listPrice']);
        $variation->setPriceCurrencyCode($variationData['priceCurrencyCode']);
        $variation->setAvailability($variationData['availability']);
        return $variation;
    }
}
