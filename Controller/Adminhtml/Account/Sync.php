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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Sdk\NostoOAuthClient;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Oauth\Builder;

class Sync extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;
    private $_accountHelper;
    private $_oauthMetaBuilder;
    private $_storeManager;

    /**
     * @param Context $context
     * @param Account $accountHelper
     * @param Builder $oauthMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
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
        /** @var Store $store */
        $store = $this->_storeManager->getStore($storeId);
        $account = !is_null($store)
            ? $this->_accountHelper->findAccount($store)
            : null;

        if (!is_null($store) && !is_null($account)) {
            $metaData = $this->_oauthMetaBuilder->build($store, $account);
            $client = new NostoOAuthClient($metaData);

            $response['success'] = true;
            $response['redirect_url'] = $client->getAuthorizationUrl();
        }

        return $this->_result->setData($response);
    }

    /**
     * Is the user allowed to view Nosto account settings
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
