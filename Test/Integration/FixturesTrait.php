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
use Magento\Review\Model\Review;
use Magento\Review\Model\Rating;
use Magento\Review\Model\ReviewFactory;
use Magento\Review\Model\RatingFactory;
use Prophecy\Prophecy\Revealer;
use Magento\Customer\Model\Group;
use Magento\Catalog\Api\ProductTierPriceManagementInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

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
        $product = self::createSimpleProduct(123, 10, '5.99');
        $categoryIds = [16];
        self::assignCategories($product, $categoryIds);
        self::assignRatingAndReview($product);
//        self::setTierPrice($product);
        return $product;
    }

    /**
     * Loads a fixture for configurable product
     */
    public static function fixtureLoadConfigurableProduct()
    {
        $product = self::createConfigurableProduct();
        $categoryIds = [16];
        self::assignCategories($product, $categoryIds);
        self::attachSkusToConfigurableProduct($product);
    }

    /**
     * Creates simple product for testing purposes
     *
     * @param int $productId
     * @param float $price
     * @param string|null $specialPrice
     * @return Product
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    private static function createSimpleProduct($productId, $price, $specialPrice = null)
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /* @var Product $product */
        $product = $objectManager->create(Product::class);
        $product
            ->setId($productId)
            ->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Nosto Simple Product '.$productId)
            ->setSku('nosto-simple-product-'.$productId)
            ->setPrice($price)
            ->setMetaTitle('Nosto Meta Title')
            ->setMetaKeyword('Nosto Mesta Keywords')
            ->setDescription('Nosto Product Description')
            ->setMetaDescription('Nosto Meta Descripption')
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setUrlKey('nosto-simple-product-'.$productId)
            ->setStockData(
                [
                    'use_config_manage_stock'   => 1,
                    'qty'                       => 100,
                    'is_qty_decimal'            => 0,
                    'is_in_stock'               => 1,
                ]
            );

        if ($specialPrice !== null) {
            $product->setSpecialPrice($specialPrice);
        }

        //Save product in database
        self::saveProduct($product);

        return $product;
    }

    private static function createConfigurableProduct()
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /* @var Product $product */
        $product = $objectManager->create(Product::class);
        $product->setTypeId(Configurable::TYPE_CODE)
            ->setId(404)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Configurable Product 404')
            ->setSku('configurable')
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setUrlKey('nosto-configurable-product-404')
            ->setDescription('Nosto Configurable Product Description')
            ->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);

        //Save product in database
        //ToDo - this fails
        //self::saveProduct($product);

        return $product;
    }

    /**
     * Attach SKU to a configurable product
     *
     * @param Product $configurableProduct
     * @throws \Exception
     */
    private static function attachSkusToConfigurableProduct(Product $configurableProduct)
    {
        //Ids and prices
        $simpleProductIds = [
            [5, 10],
            [6, 8.2],
            [7, 9.5],
            [8, 10]
        ];

        //Generate some simple products
        foreach ($simpleProductIds as $product) {
            $simpleProduct = self::createSimpleProduct($product[0], $product[1]);
        }

        $colorAttrId = $configurableProduct->getResource()
            ->getAttribute('color')
            ->getId();
        $configurableProduct->getTypeInstance()
            ->setUsedProductAttributeIds(
                array($colorAttrId),
                $configurableProduct
            );
        $configurableAttributesData = $configurableProduct->getTypeInstance()
            ->getConfigurableAttributesAsArray($configurableProduct);
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->setConfigurableAttributesData($configurableAttributesData);
        $configurableProductsData = array();
        $configurableProductsData[5] = array(
            '0' => array(
                'label' => 'Red', //attribute label
                'attribute_id' => $colorAttrId, //color attribute id
                'value_index' => '193',
                'is_percent' => 0,
                'pricing_value' => '10',
            )
        );
        $configurableProductsData[6] = array(
            '0' => array(
                'label' => 'Green', //attribute label
                'attribute_id' => $colorAttrId, //color attribute id
                'value_index' => '193',
                'is_percent' => 0,
                'pricing_value' => '10',
            )
        );
        $configurableProductsData[7] = array(
            '0' => array(
                'label' => 'Blue', //attribute label
                'attribute_id' => $colorAttrId, //color attribute id
                'value_index' => '193',
                'is_percent' => 0,
                'pricing_value' => '10',
            )
        );
        $configurableProduct->setConfigurableProductsData($configurableProductsData);
        $configurableProduct->save();

        $configurableProduct->setAssociatedProductIds([5,6,7,8]); // Assign simple product id
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->save();
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

    /**
     * @param Product $product
     */
    private static function assignRatingAndReview(Product $product)
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        $reviewFinalData['ratings'][1] = 5;
        $reviewFinalData['ratings'][2] = 5;
        $reviewFinalData['ratings'][3] = 5;
        $reviewFinalData['nickname'] = "John Doe";
        $reviewFinalData['title'] = "Create Review Programatically";
        $reviewFinalData['detail'] = "This is nice blog for magento 2.Creating product reviews programatically.";
        $productId = $product->getId();

        /* @var ReviewFactory $reviewFactory */
        $reviewFactory = $objectManager->create(ReviewFactory::class);
        $review = $reviewFactory->create()->setData($reviewFinalData);
        $review->unsetData('review_id');
        $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue($productId)
            ->setStatusId(Review::STATUS_APPROVED)//By default set approved
            ->setStoreId(1)
            ->setStores([1])
            ->save();

        /* @var RatingFactory $ratingFactory */
        $ratingFactory = $objectManager->create(RatingFactory::class);
        foreach ($reviewFinalData['ratings'] as $ratingId => $optionId) {
            $ratingFactory->create()
                ->setRatingId($ratingId)
                ->setReviewId($review->getId())
                ->addOptionVote($optionId, $productId);
        }

        $review->aggregate();
    }

    /**
     * @param Product $product
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private static function setTierPrice(Product $product)
    {
        /* @var ObjectManagerInterface */
        $objectManager = self::getStaticObjectManager();

        /* @var ProductTierPriceManagementInterface $tier */
        $tier = $objectManager->create(ProductTierPriceManagementInterface::class);

        //General
        $tier->add(
            $product->getSku(),
            1,
            7,
            1
        );

        //Wholesale
        $tier->add(
            $product->getSku(),
            2,
            7,
            1
        );

        //Retailer
        $tier->add(
            $product->getSku(),
            3,
            5,
            1
            );
    }

}