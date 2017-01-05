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
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;
use NostoHttpRequest;
use NostoOperationOrderConfirmation;
use Psr\Log\LoggerInterface;

/**
 * Class Save
 * @package Nosto\Tagging\Observer
 */
class Save implements ObserverInterface
{
    /**
     * @var NostoHelperData
     */
    protected $nostoHelperData;

    /**
     * @var NostoHelperAccount
     */
    protected $nostoHelperAccount;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var NostoOrderBuilder
     */
    protected $nostoOrderBuilder;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;
    protected $customerFactory;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     * @param CustomerFactory $customerFactory
     * @param NostoOrderBuilder $orderBuilder
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        /** @noinspection PhpUndefinedClassInspection */
        CustomerFactory $customerFactory,
        NostoOrderBuilder $orderBuilder
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoOrderBuilder = $orderBuilder;
        $this->customerFactory = $customerFactory;

        NostoHttpRequest::buildUserAgent(
            'Magento',
            $nostoHelperData->getPlatformVersion(),
            $nostoHelperData->getModuleVersion()
        );
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
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            /* @var Order $order */
            /** @noinspection PhpUndefinedMethodInspection */
            $order = $observer->getOrder();
            $nostoOrder = $this->nostoOrderBuilder->build($order);
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $this->storeManager->getStore()
            );
            if ($nostoAccount !== null) {
                $quoteId = $order->getQuoteId();
                /** @var NostoCustomer $nostoCustomer */
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer = $this->customerFactory
                    ->create()
                    ->load($quoteId, NostoCustomer::QUOTE_ID);

                $orderService = new NostoOperationOrderConfirmation($nostoAccount);
                try {
                    $orderService->send($nostoOrder, $nostoCustomer->getNostoId());

                } catch (\Exception $e) {
                    $this->logger->error(
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
