<?php

namespace Nosto\Tagging\Controller\Export;

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
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
	/**
	 * Constructor.
	 *
	 * @param Context                  $context
	 * @param ProductCollectionFactory $productCollectionFactory
	 * @param ProductVisibility        $productVisibility
	 * @param StoreManagerInterface    $storeManager
	 * @param AccountHelper            $accountHelper
	 * @param ProductBuilder           $productBuilder
	 */
	public function __construct(
		Context $context,
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
	 * @return Raw
	 */
	public function execute()
	{
		$pageSize = (int)$this->getRequest()->getParam('limit', 100);
		$currentOffset = (int)$this->getRequest()->getParam('offset', 0);
		$currentPage = ($currentOffset / $pageSize) + 1;

		$store = $this->_storeManager->getStore(true);

		/** @var \Magento\Catalog\Model\Resource\Product\Collection $collection */
		$collection = $this->_productCollectionFactory->create();
		$collection->addAttributeToSelect('*');
		$collection->addStoreFilter($store->getId());
		$collection->setVisibility($this->_productVisibility->getVisibleInSiteIds());
		$collection->addAttributeToFilter('status', ['eq' => '1']);
		$collection->setCurPage($currentPage);
		$collection->setPageSize($pageSize);
		$collection->load();

        $exportCollection = new \NostoExportCollectionProduct();
        if ($currentPage <= $collection->getLastPageNumber()) {
            foreach ($collection->getItems() as $product) {
                /** @var \Magento\Catalog\Model\Product $product */
                $nostoProduct = $this->_productBuilder->build($product, $store);
                $exportCollection[] = $nostoProduct;
            }
        }

        return $this->export($exportCollection);
	}
}
