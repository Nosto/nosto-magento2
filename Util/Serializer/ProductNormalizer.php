<?php
namespace Nosto\Tagging\Util\Serializer;
use Nosto\Object\Product\SkuCollection;
use Nosto\Object\Product\VariationCollection;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ProductNormalizer extends ObjectNormalizer
{
    /**
     * {@inheritDoc}
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        $mappedValue = $this->mapProperty($attribute, $value);
        return parent::setAttributeValue($object, $attribute, $mappedValue, $format, $context);
    }

    /**
     * @param $attribute
     * @param $value
     * @return mixed
     */
    private function mapProperty($attribute, $value)
    {
        $mappedProperties = $this->getMappedProperties();
        if (isset($mappedProperties[$attribute])) {
            return $mappedProperties[$attribute]($value);
        }
        return $value;
    }

    /**
     * @return callable[] where key is the attribute value
     */
    private function getMappedProperties()
    {
        return [
            'skus' => static function($data) {
                $collection = new SkuCollection();
                foreach ($data as $skuItem) {
                    $collection->append(SkuDenormalizer::build($skuItem));
                }
                return $collection;
            },
            'variations' => static function($data) {
                $collection = new VariationCollection();
                foreach ($data as $variationItem) {
                    $collection->append(VariationDenormalizer::build($variationItem));
                }
                return $collection;
            }
        ];
    }
}
