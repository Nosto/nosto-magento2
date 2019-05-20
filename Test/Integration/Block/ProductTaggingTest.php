<?php

namespace Nosto\Tagging\Test\Integration\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Nosto\Tagging\Block\Product as NostoProductBlock;
use Nosto\Tagging\Test\_util\ProductBuilder;
use Nosto\Tagging\Test\Integration\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;

/**
 * Tests for product tagging
 *
 * @magentoAppArea frontend
 */
class ProductTaggingTest extends TestCase
{
    const PRODUCT_REGISTRY_KEY = 'product';
    /**
     * @var NostoProductBlock
     */
    private $productBlock;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->productBlock = $this->getObjectManager()->create(NostoProductBlock::class);
    }
    /**
     * Test that we generate the Nosto product tagging correctly
     * ToDo - the fixture here is just as an example, it's not used
     * @magentoDataFixture fixtureLoadSimpleProduct
     */
    public function testProductTaggingForSimpleProduct()
    {
        /* @var ProductRepositoryInterface $productRepo */
        $product = (new ProductBuilder($this->getObjectManager()))
            ->defaultSimple()
            ->build();
        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);

        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());

        $this->assertContains('<spanclass="product_id">', $html);
        $this->assertContains('<spanclass="name">NostoSimpleProduct</span>', $html);
    }
}