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

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as AccountHelper;
use NostoExportCollectionInterface;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Export base controller that all export controllers must extend.
 */
abstract class Base extends Action
{
    const ID = 'id';
    const LIMIT = 'limit';
    const OFFSET = 'offset';
    const CREATED_AT = 'created_at';
    const ENTITY_ID = 'entity_id';

    protected $_storeManager;
    protected $_accountHelper;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param AccountHelper $accountHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        AccountHelper $accountHelper
    ) {
        parent::__construct($context);

        $this->_storeManager = $storeManager;
        $this->_accountHelper = $accountHelper;
    }

    /**
     * Encrypts the export collection and outputs it to the browser.
     *
     * @param \NostoExportCollectionInterface $collection the data collection to export.
     *
     * @return Raw
     */
    protected function export(\NostoExportCollectionInterface $collection)
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        /** @var Store $store */
        $store = $this->_storeManager->getStore(true);
        $account = $this->_accountHelper->findAccount($store);
        if ($account !== null) {
            $cipherText = \NostoExporter::export($account, $collection);
            $result->setContents($cipherText);
        }
        return $result;
    }

    /**
     * Handles the controller request, builds the query to fetch the result,
     * encrypts the JSON and returns the result
     *
     * @return Raw
     */
    public function execute()
    {
        /** @var Store $store */
        $store = $this->_storeManager->getStore(true);
        /** @var \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->getCollection($store);
        $collection->addAttributeToSelect('*');

        $id = $this->getRequest()->getParam(self::ID, false);
        if (!empty($id)) {
            $collection->addFieldToFilter(self::ENTITY_ID, $id);
        } else {
            $pageSize = (int)$this->getRequest()->getParam(self::LIMIT, 100);
            $currentOffset = (int)$this->getRequest()->getParam(self::OFFSET, 0);
            $currentPage = ($currentOffset / $pageSize) + 1;

            $collection->setCurPage($currentPage);
            $collection->setPageSize($pageSize);
            $collection->setOrder(self::CREATED_AT, $collection::SORT_ORDER_DESC);
        }
        $collection->load();

        /** @var NostoExportCollectionInterface $exportCollection */
        $exportCollection = $this->buildExportCollection($collection, $store);
        return $this->export($exportCollection);
    }

    /**
     * Abstract function that should be implemented to return the correct
     * collection object with the controller specific filters applied
     *
     * @param Store $store The store object for the current store
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection The collection
     */
    abstract protected function getCollection(Store $store);

    /**
     * Abstract function that should be implemented to return the built export
     * collection object with all the items added
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $collection
     * @param Store $store The store object for the current store
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection The collection
     */
    abstract protected function buildExportCollection($collection, Store $store);
}
