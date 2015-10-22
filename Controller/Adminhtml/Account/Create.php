<?php

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Account\Builder;
use Psr\Log\LoggerInterface;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

class Create extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;

    /**
     * @param Context               $context
     * @param Account               $accountHelper
     * @param Builder               $accountMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json                  $result
     * @param LoggerInterface       $logger
	 * @param \NostoServiceAccount $accountService
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        Builder $accountMetaBuilder,
        StoreManagerInterface $storeManager,
        Json $result,
        LoggerInterface $logger,
		\NostoServiceAccount $accountService
    ) {
        parent::__construct($context);

        $this->_accountHelper = $accountHelper;
        $this->_accountMetaBuilder = $accountMetaBuilder;
        $this->_storeManager = $storeManager;
        $this->_result = $result;
        $this->_logger = $logger;
		$this->_accountService = $accountService;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        $store = $this->_storeManager->getStore($storeId);

        if (!is_null($store)) {
            try {
                $emailAddress = $this->_request->getParam('email');
                $metaData = $this->_accountMetaBuilder->build($store);
                // todo: how to handle this class, DI?
                if (\Zend_Validate::is($emailAddress, 'EmailAddress')) {
                    /** @var \Nosto\Tagging\Model\Meta\Account\Owner $owner */
                    $owner = $metaData->getOwner();
                    $owner->setEmail($emailAddress);
                }

                $account = $this->_accountService->create($metaData);

                if ($this->_accountHelper->saveAccount($account, $store)) {
                    // todo
                    //$this->_accountHelper->updateCurrencyExchangeRates($account, $store);
                    $response['success'] = true;
                    $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                        $store,
                        $account,
                        [
                            'message_type' => \NostoMessage::TYPE_SUCCESS,
                            'message_code' => \NostoMessage::CODE_ACCOUNT_CREATE,
                        ]
                    );
                }
            } catch (\NostoException $e) {
                $this->_logger->error($e, ['exception' => $e]);
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                $store,
                null, // account creation failed, so we have none.
                [
                    'message_type' => \NostoMessage::TYPE_ERROR,
                    'message_code' => \NostoMessage::CODE_ACCOUNT_CREATE,
                ]
            );
        }

        return $this->_result->setData($response);
    }
}
