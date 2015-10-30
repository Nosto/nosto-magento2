<?php

namespace Nosto\Tagging\Controller\Export;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as AccountHelper;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Export base controller that all export controllers must extend.
 */
abstract class Base extends Action
{
    /**
     * Constructor.
     *
     * @param Context               $context
     * @param StoreManagerInterface $storeManager
     * @param AccountHelper         $accountHelper
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
		$store = $this->_storeManager->getStore(true);
		$account = $this->_accountHelper->findAccount($store);
		if ($account !== null) {
			$cipherText = \NostoExporter::export($account, $collection);
			$result->setContents($cipherText);
		}
		return $result;
	}
}
