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
        self::assignRatingAndReview($product);
//        self::setTierPrice($product);
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
            ->setUrlKey('nosto-simple-product')
            ->setStockData(
                [
                    'use_config_manage_stock'   => 1,
                    'qty'                       => 100,
                    'is_qty_decimal'            => 0,
                    'is_in_stock'               => 1,
                ]
            )
            ->setSpecialPrice('5.99');

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