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
use Nosto\Sdk\NostoException;
use Nosto\Sdk\NostoMessage;
use Nosto\Sdk\NostoOwner;
use Nosto\Sdk\NostoServiceAccount;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Account\Builder;
use Psr\Log\LoggerInterface;

class Create extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;
    private $_accountHelper;
    private $_accountMetaBuilder;
    private $_storeManager;
    private $_accountService;
    private $_logger;

    /**
     * @param Context $context
     * @param Account $accountHelper
     * @param Builder $accountMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     * @param LoggerInterface $logger
     * @param NostoServiceAccount $accountService
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        Builder $accountMetaBuilder,
        StoreManagerInterface $storeManager,
        Json $result,
        LoggerInterface $logger,
        NostoServiceAccount $accountService
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
        /** @var Store $store */
        $store = $this->_storeManager->getStore($storeId);

        if (!is_null($store)) {
            try {
                $emailAddress = $this->_request->getParam('email');
                $metaData = $this->_accountMetaBuilder->build($store);
                // todo: how to handle this class, DI?
                if (\Zend_Validate::is($emailAddress, 'EmailAddress')) {
                    /** @var NostoOwner $owner */
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
                            'message_type' => NostoMessage::TYPE_SUCCESS,
                            'message_code' => NostoMessage::CODE_ACCOUNT_CREATE,
                        ]
                    );
                }
            } catch (NostoException $e) {
                $this->_logger->error($e, ['exception' => $e]);
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                $store,
                null, // account creation failed, so we have none.
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CREATE,
                ]
            );
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
