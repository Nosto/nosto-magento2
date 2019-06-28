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
 * @magentoDbIsolation disabled
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
        $this->unsetRegistry(self::PRODUCT_REGISTRY_KEY);
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
        $this->assertContains('<spanclass="name">NostoSimpleProduct123</span>', $html);
        $this->assertContains('<spanclass="price">5.99</span>', $html);
        $this->assertContains('<spanclass="list_price">10.0000</span>', $html);
        $this->assertContains('<spanclass="description">NostoProductDescription</span>', $html);
        $this->assertContains('<spanclass="url">http://localhost/index.php/nosto-simple-product-123.html</span>', $html);
        $this->assertContains('<spanclass="categories"><spanclass="category">/Men/Tops/Hoodies&amp;Sweatshirts</span></span>', $html);
        $this->assertContains('<spanclass="price_currency_code">USD</span>', $html);
        $this->assertContains('<spanclass="availability">InStock</span>', $html);
//        Since the isolation is disabled we need to handle these differently as the review count keeps growing
//        $this->assertContains('<spanclass="review_count">14</span>', $html);
//        $this->assertContains('<spanclass="rating_value">0</span>', $html);
        $this->assertContains('<spanclass="alternate_image_urls"></span>', $html);
        $this->assertContains('<spanclass="tags1"><spanclass="tag">add-to-cart</span></span>', $html);
        $this->assertContains('<spanclass="tags2"></span>', $html);
        $this->assertContains('<spanclass="tags3"></span>', $html);
        $this->assertContains('<spanclass="skus"></span>', $html);
        $this->assertContains('<spanclass="variation_id">USD</span>', $html);
        $this->assertContains('<spanclass="variations"></span>', $html);
    }

    /**
     * @magentoDataFixture fixtureLoadConfigurableProduct
     */
    public function testConfigurableProductTagging()
    {
        $this->enableSkuVariations();
        $product = $this->productRepository->getById(404);
        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);
        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());

        //Core product tagging
        $this->assertContains('<spanclass="product_id">404</span>', $html);
        $this->assertContains('<spanclass="name">ConfigurableProduct404</span>', $html);
        $this->assertContains('<spanclass="price">8.2</span>', $html);
        $this->assertContains('<spanclass="list_price">8.2000</span>', $html);
        $this->assertContains('<spanclass="description">NostoConfigurableProductDescription</span>', $html);
        $this->assertContains('<spanclass="url">http://localhost/index.php/nosto-configurable-product-404.html</span>', $html);
        $this->assertContains('<spanclass="categories"><spanclass="category">/Men/Tops/Hoodies&amp;Sweatshirts</span></span>', $html);
        $this->assertContains('<spanclass="price_currency_code">USD</span>', $html);
        $this->assertContains('<spanclass="availability">InStock</span>', $html);
//        $this->assertContains('<spanclass="alternate_image_urls"></span>', $html);
        $this->assertContains('<spanclass="tags1"></span>', $html);
        $this->assertContains('<spanclass="tags2"></span>', $html);
        $this->assertContains('<spanclass="tags3"></span>', $html);
        $this->assertContains('<spanclass="skus">', $html);
        $this->assertContains('<spanclass="variation_id">USD</span>', $html);
        $this->assertContains('<spanclass="variations"></span>', $html);

        //Skus tagging
        $this->assertContains('<spanclass="id">6</span>', $html);
        // ToDo - add more SKU specific assertions
    }

    /**
     * @magentoDataFixture fixtureLoadConfigurableProduct
     */
    public function testConfigurableProductTaggingWithSkuDisabled()
    {
        $this->disableSkuVariations();
        $product = $this->productRepository->getById(404);
        $this->setRegistry(self::PRODUCT_REGISTRY_KEY, $product);
        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());

        //Core product tagging
        $this->assertContains('<spanclass="product_id">404</span>', $html);
        $this->assertContains('<spanclass="name">ConfigurableProduct404</span>', $html);
        $this->assertContains('<spanclass="price">8.2</span>', $html);
        $this->assertContains('<spanclass="list_price">8.2000</span>', $html);
        $this->assertContains('<spanclass="description">NostoConfigurableProductDescription</span>', $html);
        $this->assertContains('<spanclass="url">http://localhost/index.php/nosto-configurable-product-404.html</span>', $html);
        $this->assertContains('<spanclass="categories"><spanclass="category">/Men/Tops/Hoodies&amp;Sweatshirts</span></span>', $html);
        $this->assertContains('<spanclass="price_currency_code">USD</span>', $html);
        $this->assertContains('<spanclass="availability">InStock</span>', $html);
//        $this->assertContains('<spanclass="alternate_image_urls"></span>', $html);
        $this->assertContains('<spanclass="tags1"></span>', $html);
        $this->assertContains('<spanclass="tags2"></span>', $html);
        $this->assertContains('<spanclass="tags3"></span>', $html);
        $this->assertContains('<spanclass="variation_id">USD</span>', $html);
        $this->assertContains('<spanclass="variations"></span>', $html);

        //Skus tagging
        $this->assertContains('<spanclass="skus"></span>', $html);
    }
}