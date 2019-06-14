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
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->productBlock = $this->getObjectManager()->create(NostoProductBlock::class);
        $this->productRepository = $this->getObjectManager()->create(ProductRepositoryInterface::class);
    }
    
    /**
     * Test that we generate the Nosto product tagging correctly
     * @magentoDataFixture fixtureLoadSimpleProduct
     */
    public function testProductTaggingForSimpleProduct()
    {
        $this->enableRatingsAndReviews();

        $product = $this->productRepository->getById(123);

        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);

        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());

        $this->assertContains('<spanclass="product_id">123</span>', $html);
        $this->assertContains('<spanclass="name">NostoSimpleProduct</span>', $html);
        $this->assertContains('<spanclass="price">5.99</span>', $html);
        $this->assertContains('<spanclass="list_price">10.000000</span>', $html);
        $this->assertContains('<spanclass="description">NostoProductDescription</span>', $html);
        $this->assertContains('<spanclass="url">http://localhost/index.php/nosto-simple-product.html</span>', $html);
        $this->assertContains('<spanclass="categories"><spanclass="category">/Training/VideoDownload</span></span>', $html);
        $this->assertContains('<spanclass="price_currency_code">USD</span>', $html);
        $this->assertContains('<spanclass="availability">InStock</span>', $html);
        $this->assertContains('<spanclass="review_count">1</span>', $html);
        $this->assertContains('<spanclass="rating_value">0</span>', $html);
        $this->assertContains('<spanclass="alternate_image_urls"></span>', $html);
        $this->assertContains('<spanclass="tags1"><spanclass="tag">add-to-cart</span></span>', $html);
        $this->assertContains('<spanclass="tags2"></span>', $html);
        $this->assertContains('<spanclass="tags3"></span>', $html);
        $this->assertContains('<spanclass="skus"></span>', $html);
        $this->assertContains('<spanclass="variation_id">USD</span>', $html);
        $this->assertContains('<spanclass="variations"></span>', $html);
    }

    /**
     * Test that product price variations are generated correctly
     * @magentoDataFixture fixtureLoadSimpleProduct
     */
    public function testProductTaggingWithVariations()
    {
        $this->enableVariations();

        $product = $this->productRepository->getById(123);

        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);

        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());
    }

    /**
     * @magentoDataFixture fixtureLoadConfigurableProduct
     */
    public function testConfigurableProductTagging()
    {
        $product = $this->productRepository->getById(404);
        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);
        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());

        $this->assertContains('<spanclass="nosto_sku"><spanclass="id">5</span><spanclass="name">NostoSimpleProduct5</span><spanclass="price">5.99</span><spanclass="list_price">10.000000</span><spanclass="availability">InStock</span></span>', $html);
        $this->assertContains('<spanclass="nosto_sku"><spanclass="id">6</span><spanclass="name">NostoSimpleProduct6</span><spanclass="price">5.99</span><spanclass="list_price">10.000000</span><spanclass="availability">InStock</span></span>', $html);
        $this->assertContains('<spanclass="nosto_sku"><spanclass="id">7</span><spanclass="name">NostoSimpleProduct7</span><spanclass="price">5.99</span><spanclass="list_price">10.000000</span><spanclass="availability">InStock</span></span>', $html);
        $this->assertContains('<spanclass="nosto_sku"><spanclass="id">8</span><spanclass="name">NostoSimple\Product8</span><spanclass="price">5.99</span><spanclass="list_price">10.000000</span><spanclass="availability">InStock</span></span>', $html);
    }
}