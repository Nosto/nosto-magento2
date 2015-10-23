<?php

namespace Nosto\Tagging\Model\Category;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Model\Category\Factory as CategoryFactory;

class Builder
{
	/**
	 * @var CategoryFactory
	 */
	protected $_categoryFactory;

	/**
	 * @var LoggerInterface
	 */
	protected $_logger;

	/**
	 * @param CategoryFactory $productFactory
     * @param CategoryRepositoryInterface $categoryRepository
	 * @param LoggerInterface $logger
	 */
	public function __construct(
        CategoryFactory $productFactory,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
	) {
		$this->_categoryFactory = $productFactory;
        $this->_categoryRepository = $categoryRepository;
		$this->_logger = $logger;
	}

	/**
     * @param Category $category
	 * @return \Nosto\Tagging\Model\Category
	 */
	public function build(Category $category)
	{
		$nostoCategory = $this->_categoryFactory->create();

		try {
            $nostoCategory->setPath($this->buildPath($category));
		} catch (\NostoException $e) {
			$this->_logger->error($e, ['exception' => $e]);
		}

		return $nostoCategory;
	}

    /**
     * @param Category $category
     * @return string
     */
    protected function buildPath(Category $category)
    {
        $data = [];
        $path = $category->getPath();
        foreach (explode('/', $path) as $categoryId) {
            $category = $this->_categoryRepository->get($categoryId);
            if ($category && $category->getLevel() > 1) {
                $data[] = $category->getName();
            }
        }
        return count($data) ? '/'.implode('/', $data) : '';
    }
}
