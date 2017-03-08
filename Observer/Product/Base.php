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

namespace Nosto\Tagging\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Sdk\NostoAccount;
use Nosto\Sdk\NostoException;
use Nosto\Sdk\NostoProduct;
use Nosto\Sdk\NostoServiceProduct;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;
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
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->_moduleManager->isEnabled(DataHelper::MODULE_NAME)) {
            // Always "delete" the product for all stores it is available in.
            // This is done to avoid data inconsistencies as even if a product
            // is edited for only one store, the updated data can reflect in
            // other stores as well.
            /* @var \Magento\Catalog\Model\Product $product */
            /** @noinspection PhpUndefinedMethodInspection */
            $product = $observer->getProduct();
            foreach ($product->getStoreIds() as $storeId) {
                /** @var Store $store */
                $store = $this->_storeManager->getStore($storeId);
                /** @var NostoAccount $account */
                $account = $this->_accountHelper->findAccount($store);
                if ($account === null) {
                    continue;
                }

                if (!$this->validateProduct($product)) {
                    continue;
                }

                // Load the product model for this particular store view.
                /** @var NostoProduct $model */
                $metaProduct = $this->_productBuilder->build($product, $store);
                if (is_null($metaProduct)) {
                    continue;
                }

                try {
                    $op = new NostoServiceProduct($account);
                    $op->addProduct($metaProduct);
                    $this->doRequest($op);
                } catch (NostoException $e) {
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
     * @param NostoServiceProduct $operation
     * @return mixed
     */
    abstract protected function doRequest(NostoServiceProduct $operation);
}