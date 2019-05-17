<?php
/** @var $product \Magento\Catalog\Model\Product */
$repository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
    'Magento\Catalog\Model\ProductRepository'
);
try {
    $product = $repository->get('simple');
    $product->delete();
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    //Entity already deleted
}