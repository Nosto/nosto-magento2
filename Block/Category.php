<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Category block used for outputting meta-data on the stores category pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Category extends Template
{
    /**
     * @inheritdoc
     */
    protected $_template = 'category.phtml';

    /**
     * @var Registry the framework registry.
     */
    protected $_registry;

    /**
     * @var CategoryBuilder the category meta model builder.
     */
    protected $_categoryBuilder;

    /**
     * Constructor.
     *
     * @param Template\Context $context
     * @param Registry         $registry
     * @param CategoryBuilder  $categoryBuilder
     * @param array            $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        CategoryBuilder $categoryBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->_registry = $registry;
        $this->_categoryBuilder = $categoryBuilder;
    }

    /**
     * Returns the Nosto category meta-data model.
     *
     * @return \Nosto\Tagging\Model\Category the category meta data model.
     */
    public function getNostoCategory()
    {
        $category = $this->_registry->registry('current_category');
        return $this->_categoryBuilder->build($category);
    }
}
