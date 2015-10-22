<?php

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;

    /**
     * @param Context               $context
     * @param Account               $accountHelper
     * @param StoreManagerInterface $storeManager
     * @param Json                  $result
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

        $this->_accountHelper = $accountHelper;
        $this->_storeManager = $storeManager;
        $this->_result = $result;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        $store = $this->_storeManager->getStore($storeId);
        $account = !is_null($store)
            ? $this->_accountHelper->findAccount($store)
            : null;

        if (!is_null($store) && !is_null($account)) {
            if ($this->_accountHelper->deleteAccount($account, $store)) {
                $response['success'] = true;
                $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                    $store,
                    null, // we don't have an account anymore
                    [
                        'message_type' => \NostoMessage::TYPE_SUCCESS,
                        'message_code' => \NostoMessage::CODE_ACCOUNT_DELETE,
                    ]
                );
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                $store,
                $account,
                [
                    'message_type' => \NostoMessage::TYPE_ERROR,
                    'message_code' => \NostoMessage::CODE_ACCOUNT_DELETE,
                ]
            );
        }

        return $this->_result->setData($response);
    }
}
