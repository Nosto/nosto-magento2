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

namespace Nosto\Tagging\Controller\Export;

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use /** @noinspection PhpUndefinedClassInspection */
    Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;

/**
 * Product export controller used to export product history to Nosto in order to
 * bootstrap the recommendations during initial account creation.
 * This controller will be called by Nosto when a new account has been created
 * from the Magento backend. The controller is public, but the information is
 * encrypted with AES, and only Nosto can decrypt it.
 */
class Product extends Base
{

    private $_productCollectionFactory;
    private $_productVisibility;
    private $_productBuilder;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Context $context
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductVisibility $productVisibility
     * @param StoreManagerInterface $storeManager
     * @param AccountHelper $accountHelper
     * @param ProductBuilder $productBuilder
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        ProductCollectionFactory $productCollectionFactory,
        ProductVisibility $productVisibility,
        StoreManagerInterface $storeManager,
        AccountHelper $accountHelper,
        ProductBuilder $productBuilder
    ) {
        parent::__construct($context, $storeManager, $accountHelper);

        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productVisibility = $productVisibility;
        $this->_productBuilder = $productBuilder;
    }

    /**
     * @inheritdoc
     */
    protected function getCollection(Store $store)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->_productCollectionFactory->create();
        $collection->setVisibility($this->_productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter('status', ['eq' => '1']);
        $collection->addStoreFilter($store->getId());
        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function buildExportCollection($collection, Store $store)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $exportCollection = new \NostoExportProductCollection();
        $items = $collection->loadData();
        foreach ($items as $product) {
            /** @var \Magento\Catalog\Model\Product $product */
            $exportCollection[] = $this->_productBuilder->build($product, $store);
        }
        return $exportCollection;
    }
}