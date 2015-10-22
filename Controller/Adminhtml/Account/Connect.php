<?php

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Model\Meta\Oauth\Builder;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

class Connect extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;

    /**
     * @param Context               $context
     * @param Builder               $oauthMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json                  $result
     */
    public function __construct(
        Context $context,
        Builder $oauthMetaBuilder,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

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

        if (!is_null($store)) {
            $metaData = $this->_oauthMetaBuilder->build($store);
            $client = new \NostoOAuthClient($metaData);

            $response['success'] = true;
            $response['redirect_url'] = $client->getAuthorizationUrl();
        }

        return $this->_result->setData($response);
    }
}
