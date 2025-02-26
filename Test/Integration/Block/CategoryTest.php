<?php

namespace Nosto\Tagging\Test\Integration\Block;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use Nosto\Model\Category\Category as NostoCategory;
use Nosto\Tagging\Block\Category;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class CategoryTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManager;
    
    /** @var Registry */
    private $registry;
    
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;
    
    /** @var Category */
    private $block;
    
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->registry = $this->objectManager->get(Registry::class);
        $this->categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);
        $this->block = $this->objectManager->get(Category::class);
    }
    
    /**
     * @magentoDataFixture Magento/Catalog/_files/category.php
     */
    public function testGetAbstractObject()
    {
        $category = $this->categoryRepository->get(333);
        $this->registry->register('current_category', $category);
        
        $nostoCategory = $this->block->getAbstractObject();
        $this->assertInstanceOf(NostoCategory::class, $nostoCategory);
        
        // Test that we properly build the category path string
        $categoryString = $nostoCategory->disableAutoEncodeAll()->__toString();
        $this->assertStringContainsString($category->getName(), $categoryString);
    }
    
    public function testGetAbstractObjectWithoutCategory()
    {
        $nostoCategory = $this->block->getAbstractObject();
        $this->assertNull($nostoCategory);
    }
}
