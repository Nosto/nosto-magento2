<?php
namespace Nosto\Tagging\Util\Serializer;

use Nosto\Object\Product\Sku;

class SkuDenormalizer
{
    /**
     * @param array $skuData
     * @return Sku
     */
    public static function build(array $skuData)
    {
        $sku = new Sku();
        $sku->setId($skuData['id']);
        $sku->setName($skuData['name']);
        $sku->setPrice($skuData['price']);
        $sku->setListPrice($skuData['listPrice']);
        $sku->setUrl($skuData['url']);
        $sku->setImageUrl($skuData['imageUrl']);
        $sku->setGtin($skuData['gtin']);
        $sku->setAvailability($skuData['availability']);
        $sku->setCustomFields($skuData['customFields']);
        $sku->setInventoryLevel($skuData['inventoryLevel']);
       return $sku;
    }
}