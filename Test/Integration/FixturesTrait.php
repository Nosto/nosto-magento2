<?php
namespace Nosto\Tagging\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;

trait FixturesTrait
{
    /**
     * @return ObjectManagerInterface
     */
    public static function getStaticObjectManager()
    {
        return Bootstrap::getObjectManager();
    }

    /**
     * Loads a fixture for simple product
     */
    public static function fixtureLoadSimpleProduct()
    {
        //ToDo - for whatever reason we cannot use the builder and save
        // the product after building

        /** @var Product $product */
        $product = self::getStaticObjectManager()
            ->create('Magento\Catalog\Model\Product');
        $product->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Nosto Simple Product')
            ->setSku('nosto-simple-product')
            ->setPrice(10)
            ->setMetaTitle('Nosto Meta Title')
            ->setMetaKeyword('Nosto Mesta Keywords')
            ->setDescription('Nosto Product Description')
            ->setMetaDescription('Nosto Meta Descripption')
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setSpecialPrice('5.99')
            ->save();
    }
}