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

namespace Nosto\Tagging\Observer\Order;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Sdk\NostoServiceOrder;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Magento\Framework\Event\ObserverInterface;
use Nosto\Tagging\Model\Order\Builder;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use Nosto\Tagging\Model\CustomerFactory;
use Nosto\Tagging\Api\Data\CustomerInterface as NostoCustomer;

/**
 * Class Save
 * @package Nosto\Tagging\Observer
 */
class Save implements ObserverInterface
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
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var Builder
     */
    protected $_orderBuilder;

    /**
     * @var ModuleManager
     */
    protected $_moduleManager;

    /**
     * @var CustomerFactory
     */
    protected $_customerFactory;
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpUndefinedClassInspection */

    /**
     * Constructor.
     *
     * @param DataHelper $dataHelper
     * @param AccountHelper $accountHelper
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     * @param CustomerFactory $customerFactory
     * @param OrderBuilder $orderBuilder
     */
    public function __construct(
        DataHelper $dataHelper,
        AccountHelper $accountHelper,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        CustomerFactory $customerFactory,
        OrderBuilder $orderBuilder
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_accountHelper = $accountHelper;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
        $this->_moduleManager = $moduleManager;
        $this->_orderBuilder = $orderBuilder;
        $this->_customerFactory = $customerFactory;
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
            /* @var Order $order */
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $observer->getOrder();
            $nostoOrder = $this->_orderBuilder->build($order);
            $nostoAccount = $this->_accountHelper->findAccount(
                $this->_storeManager->getStore()
            );
            if ($nostoAccount !== null) {
                $quoteId = $order->getQuoteId();
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer = $this->_customerFactory
                    ->create()
                    ->load($quoteId, NostoCustomer::QUOTE_ID);

                $orderService = new NostoServiceOrder($nostoAccount);
                try {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $orderService->confirm($nostoOrder,
                        $nostoCustomer->getNostoId());

                } catch (\Exception $e) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    $this->_logger->error(
                        sprintf(
                            "Failed to save order with quote #%s for customer #%s.
                        Message was: %s",
                            $quoteId,
                            $nostoCustomer->getNostoId(),
                            $e->getMessage()
                        )
                    );
                }
            }
        }
    }
}
