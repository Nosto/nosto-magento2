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

use Magento\Framework\App\Action\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;

/**
 * Order export controller used to export order history to Nosto in order to
 * bootstrap the recommendations during initial account creation.
 * This controller will be called by Nosto when a new account has been created
 * from the Magento backend. The controller is public, but the information is
 * encrypted with AES, and only Nosto can decrypt it.
 */
class Order extends Base
{

    private $_orderCollectionFactory;
    private $_orderBuilder;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Context $context
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param AccountHelper $accountHelper
     * @param OrderBuilder $orderBuilder
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        OrderCollectionFactory $orderCollectionFactory,
        StoreManagerInterface $storeManager,
        AccountHelper $accountHelper,
        OrderBuilder $orderBuilder
    ) {
        parent::__construct($context, $storeManager, $accountHelper);

        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_orderBuilder = $orderBuilder;
    }

    /**
     * @inheritdoc
     */
    protected function getCollection(Store $store)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->_orderCollectionFactory->create();
        $collection->addAttributeToFilter('store_id', ['eq' => $store->getId()]);
        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function buildExportCollection($collection, Store $store)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $exportCollection = new \NostoExportOrderCollection();
        foreach ($collection->getItems() as $order) {
            /** @var \Magento\Sales\Model\Order $order */
            $exportCollection[] = $this->_orderBuilder->build($order);
        }
        return $exportCollection;
    }
}