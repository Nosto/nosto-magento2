<?php

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Oauth\Builder;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

class Sync extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;

    /**
     * @param Context               $context
     * @param Account               $accountHelper
     * @param Builder               $oauthMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json                  $result
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        Builder $oauthMetaBuilder,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

        $this->_accountHelper = $accountHelper;
        $this->_oauthMetaBuilder = $oauthMetaBuilder;
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
            $metaData = $this->_oauthMetaBuilder->build($store, $account);
            $client = new \NostoOAuthClient($metaData);

            $response['success'] = true;
            $response['redirect_url'] = $client->getAuthorizationUrl();
        }

        return $this->_result->setData($response);
    }
}
