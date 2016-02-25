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
     * @param Registry $registry
     * @param CategoryBuilder $categoryBuilder
     * @param array $data
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
     * @return \NostoCategory the category meta data model.
     */
    public function getNostoCategory()
    {
        $category = $this->_registry->registry('current_category');
        return $this->_categoryBuilder->build($category);
    }
}
