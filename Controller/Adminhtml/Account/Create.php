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

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Account\Builder as SignupBuilder;
use Psr\Log\LoggerInterface;

class Create extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $_result;
    private $_accountHelper;
    private $_signupBuilder;
    private $_storeManager;
    private $_logger;

    /**
     * @param Context $context
     * @param Account $accountHelper
     * @param SignupBuilder $signupBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        SignupBuilder $signupBuilder,
        StoreManagerInterface $storeManager,
        Json $result,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_accountHelper = $accountHelper;
        $this->_signupBuilder = $signupBuilder;
        $this->_storeManager = $storeManager;
        $this->_result = $result;
        $this->_logger = $logger;
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
                $signupParams = $this->_signupBuilder->build($store);
                // todo: how to handle this class, DI?
                if (\Zend_Validate::is($emailAddress, 'EmailAddress')) {
                    /** @var \NostoSignupOwner $owner */
                    $owner = $signupParams->getOwner();
                    $owner->setEmail($emailAddress);
                } else {
                    throw new \NostoException("Invalid email address " . $emailAddress);
                }

                $operation = new \NostoOperationAccount($signupParams);
                $account = $operation->create();

                if ($this->_accountHelper->saveAccount($account, $store)) {
                    // todo
                    //$this->_accountHelper->updateCurrencyExchangeRates($account, $store);
                    $response['success'] = true;
                    $response['redirect_url'] = $this->_accountHelper->getIframeUrl(
                        $store,
                        $account,
                        $owner,
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
