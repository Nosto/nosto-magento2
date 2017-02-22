<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Model\Category;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Nosto\Tagging\Model\Category\Factory as CategoryFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

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
     * @return \Nosto\Sdk\NostoCategory
     */
    public function build(Category $category)
    {
        $nostoCategory = $this->_categoryFactory->create();

        try {
            $nostoCategory->setPath($this->buildPath($category));
        } catch (\Nosto\Sdk\NostoException $e) {
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
            try {
                $category = $this->_categoryRepository->get($categoryId);
                if ($category && $category->getLevel() > 1) {
                    $data[] = $category->getName();
                }
            } catch (NoSuchEntityException $e) {
                // No need for further processing
            }
        }

        return count($data) ? '/' . implode('/', $data) : '';
    }
}
