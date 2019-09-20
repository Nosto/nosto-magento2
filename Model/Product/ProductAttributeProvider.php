<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Framework\Phrase;
use Nosto\Helper\ArrayHelper;
use Nosto\Object\Product\Product;

abstract class ProductAttributeProvider implements ProductProvider
{
    /**
     * Resolves "textual" product attribute value
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param $attribute
     * @return bool|float|int|null|string
     */
    public function getAttributeValue(Product $product, $attribute)
    {
        $value = null;
        $attributes = $product->getAttributes();
        if (isset($attributes[$attribute])) {
            $attributeObject = $attributes[$attribute];
            $frontend = $attributeObject->getFrontend();
            $frontendValue = $frontend->getValue($product);
            if (is_array($frontendValue) && !empty($frontendValue)
                && ArrayHelper::onlyScalarValues($frontendValue)
            ) {
                $value = implode(',', $frontendValue);
            } elseif (is_scalar($frontendValue)) {
                $value = $frontendValue;
            } elseif ($frontendValue instanceof Phrase) {
                $value = (string)$frontendValue;
            }
        }

        return $value;
    }
}