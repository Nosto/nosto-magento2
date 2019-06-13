<?php
namespace Nosto\Tagging\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Type;

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
        $product = self::createSimpleProduct();
        self::saveProduct($product);
        $categoryIds = [16];
        self::assignCategories($product, $categoryIds);
        return $product;
    }

    /**
     * Creates simple product for testing purposes
     *
     * @return Product $product
     */
    private static function createSimpleProduct()
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /* @var Product $product */
        $product = $objectManager->create(Product::class);
        $product
            ->setId(123)
            ->setTypeId(Type::TYPE_SIMPLE)
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
            ->setStockData(
                [
                    'use_config_manage_stock'   => 1,
                    'qty'                       => 100,
                    'is_qty_decimal'            => 0,
                    'is_in_stock'               => 1,
                ]
            )
            ->setSpecialPrice('5.99')
            ->setTierPrice(
                [
                    [
                        'website_id' => 0,
                        'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                        'price_qty'  => 1,
                        'price'      => 8,
                    ],
                    [
                        'website_id' => 0,
                        'cust_group' => \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID,
                        'price_qty'  => 1,
                        'price'      => 7,
                    ],
                ]
            );

        return $product;
    }

    /**
     * Stores product in database
     *
     * @param Product $product
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private static function saveProduct(Product $product)
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $productRepository->save($product);
    }

    /**
     * Assign categories to specific product
     *
     * @param Product $product
     * @param array $categories
     */
    private static function assignCategories(Product $product, array $categories)
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /** @var CategoryLinkManagementInterface $categoryLinkManager */
        $categoryLinkManager = $objectManager->create(CategoryLinkManagementInterface::class);
        $categoryLinkManager->assignProductToCategories(
            $product->getSku(),
            $categories
        );
    }
}