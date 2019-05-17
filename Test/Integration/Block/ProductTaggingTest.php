<?php

namespace Nosto\Tagging\Test\Integration\Block;

use Nosto\Tagging\Block\Product as NostoProductBlock;
use Nosto\Tagging\Test\Integration\TestCase;
use Magento\Catalog\Model\Product;

/**
 * @magentoAppArea frontend
 */
class ProductTaggingTest extends TestCase
{
    /**
     * @var Embed
     */
    private $productBlock;

    public function setUp()
    {
        parent::setUp();
        $this->productBlock = $this->getObjectManager()->create(NostoProductBlock::class);
    }
    /**
     * Test that we generate the Nosto product tagging correctly
     * @magentoDataFixture  ./_files/product.php
     */
    public function testProductTagging()
    {
        $product = $this->getObjectManager()->create(Product::class);
        $product->load(1);
//        $this->_coreRegistry->register('product', $product);

        $html = self::stripAllWhiteSpace($this->productBlock->toHtml());
        $needle = self::stripAllWhiteSpace(
            sprintf('/include/%s" async></script>', self::DEFAULT_NOSTO_ACCOUNT)
        );
//        $this->assertContains($needle, $html);
    }
}