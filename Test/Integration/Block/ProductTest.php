<?php

namespace Nosto\Tagging\Test\Integration\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Block\Product;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class ProductTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManager;
    
    /** @var Registry */
    private $registry;
    
    /** @var ProductRepositoryInterface */
    private $productRepository;
    
    /** @var Product */
    private $block;
    
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->registry = $this->objectManager->get(Registry::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->block = $this->objectManager->get(Product::class);
    }
    
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testGetAbstractObject()
    {
        $product = $this->productRepository->get('simple');
        $this->registry->register('product', $product);
        $this->registry->register('current_product', $product);
        
        $nostoProduct = $this->block->getAbstractObject();
        $this->assertInstanceOf(NostoProduct::class, $nostoProduct);
        $this->assertEquals($product->getId(), $nostoProduct->getProductId());
    }
    
    /**
     * @magentoConfigFixture current_store nosto_tagging/currency/use_multiple_currencies 1
     */
    public function testHasMultipleCurrencies()
    {
        $result = $this->block->hasMultipleCurrencies();
        $this->assertTrue($result);
    }
    
    /**
     * @magentoConfigFixture current_store nosto_tagging/currency/use_multiple_currencies 0
     */
    public function testDoesNotHaveMultipleCurrencies()
    {
        $result = $this->block->hasMultipleCurrencies();
        $this->assertFalse($result);
    }
}
