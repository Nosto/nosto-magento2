<?php
namespace Nosto\Tagging\Test\_util;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;

class ProductBuilder implements BuilderInterface
{
    /**
     * @var Product
     */
    private $product;

    public function __construct(ObjectManagerInterface $manager)
    {
        /** @var Product $product */
        $this->product = $manager->create('Magento\Catalog\Model\Product');
    }

    /**
     * @param $name
     * @param $type
     * @return $this
     */
    public function defaultSimple()
    {
        $this->product->setTypeId('simple')
            ->setId(123)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Nosto Simple Product')
            ->setSku('nosto-simple-sku')
            ->setPrice(10)
            ->setMetaTitle('Nosto Meta Title')
            ->setMetaKeyword('Nosto Mesta Keywords')
            ->setDescription('Nosto Product Description')
            ->setMetaDescription('Nosto Meta Descripption')
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setSpecialPrice('5.99');

        return $this;
    }

    /**
     * @return Product
     */
    public function build()
    {
        return $this->product;
    }
}