<?php

namespace Nosto\Tagging\Controller\Export;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as AccountHelper;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use NostoExportCollectionOrder;

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
        $exportCollection = new NostoExportCollectionOrder();
        foreach ($collection->getItems() as $order) {
            /** @var \Magento\Sales\Model\Order $order */
            $nostoOrder = $this->_orderBuilder->build($order, $store);
            $exportCollection[] = $nostoOrder;
        }
        return $exportCollection;
    }
}