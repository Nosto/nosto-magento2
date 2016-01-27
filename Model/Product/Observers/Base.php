<?php

namespace Nosto\Tagging\Model\Product\Observers;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

abstract class Base implements ObserverInterface
{
    /**
     * @var DataHelper
     */
    protected $_dataHelper;

    /**
     * @var AccountHelper
     */
    protected $_accountHelper;

    /**
     * @var ProductBuilder
     */
    protected $_productBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var ModuleManager
     */
    protected $_moduleManager;

    /**
     * Constructor.
     *
     * @param DataHelper $dataHelper
     * @param AccountHelper $accountHelper
     * @param ProductBuilder $productBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        DataHelper $dataHelper,
        AccountHelper $accountHelper,
        ProductBuilder $productBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ModuleManager $moduleManager
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_accountHelper = $accountHelper;
        $this->_productBuilder = $productBuilder;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
        $this->_moduleManager = $moduleManager;
    }

    /**
     * Event handler for the "catalog_product_save_after" and  event.
     * Sends a product update API call to Nosto.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->_moduleManager->isEnabled('Nosto_Tagging')) {
            // Always "delete" the product for all stores it is available in.
            // This is done to avoid data inconsistencies as even if a product
            // is edited for only one store, the updated data can reflect in
            // other stores as well.
            /* @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getProduct();
            foreach ($product->getStoreIds() as $storeId) {
                /** @var Store $store */
                $store = $this->_storeManager->getStore($storeId);
                /** @var \NostoAccount $account */
                $account = $this->_accountHelper->findAccount($store);
                if ($account === null) {
                    continue;
                }

                if (!$this->validateProduct($product)) {
                    continue;
                }

                // Load the product model for this particular store view.
                /** @var \NostoProduct $model */
                $metaProduct = $this->_productBuilder->build($product, $store);
                if (is_null($metaProduct)) {
                    continue;
                }

                try {
                    $op = new \NostoServiceProduct($account);
                    $op->addProduct($metaProduct);
                    $this->doRequest($op);
                } catch (\NostoException $e) {
                    $this->_logger->error($e, ['exception' => $e]);
                }
            }
        }
    }

    /**
     * Validate whether the event should be handled or not
     *
     * @param Product $product the product from the event
     */
    abstract protected function validateProduct(Product $product);

    /**
     * @param \NostoServiceProduct $operation
     * @return mixed
     */
    abstract protected function doRequest(\NostoServiceProduct $operation);
}