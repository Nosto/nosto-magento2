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
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Account\Builder as NostoSignupBuilder;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use NostoException;
use NostoHelperIframe;
use NostoMessage;
use NostoOperationAccount;
use NostoSignupOwner;
use Psr\Log\LoggerInterface;

class Create extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $result;
    private $nostoHelperAccount;
    private $nostoCurrentUserBuilder;
    /**
     * @var NostoIframeMetaBuilder
     */
    private $nostoIframeMetaBuilder;
    private $nostoSignupBuilder;
    private $storeManager;
    private $logger;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoSignupBuilder $nostoSignupBuilder
     * @param NostoIframeMetaBuilder $nostoIframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoSignupBuilder $nostoSignupBuilder,
        NostoIframeMetaBuilder $nostoIframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        StoreManagerInterface $storeManager,
        Json $result,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoSignupBuilder = $nostoSignupBuilder;
        $this->nostoIframeMetaBuilder = $nostoIframeMetaBuilder;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
        $this->storeManager = $storeManager;
        $this->result = $result;
        $this->logger = $logger;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);

        if (!is_null($store)) {
            try {
                $emailAddress = $this->_request->getParam('email');
                $signupParams = $this->nostoSignupBuilder->build($store);
                if (\Zend_Validate::is($emailAddress, 'EmailAddress')) {
                    /** @var NostoSignupOwner $owner */
                    $owner = $signupParams->getOwner();
                    $owner->setEmail($emailAddress);
                } else {
                    throw new NostoException("Invalid email address " . $emailAddress);
                }

                $operation = new NostoOperationAccount($signupParams);
                $account = $operation->create();

                if ($this->nostoHelperAccount->saveAccount($account, $store)) {
                    $response['success'] = true;
                    $response['redirect_url'] = NostoHelperIframe::getUrl(
                        $this->nostoIframeMetaBuilder->build($store),
                        $account,
                        $this->nostoCurrentUserBuilder->build(),
                        [
                            'message_type' => NostoMessage::TYPE_SUCCESS,
                            'message_code' => NostoMessage::CODE_ACCOUNT_CREATE,
                        ]
                    );
                }
            } catch (NostoException $e) {
                $this->logger->error($e, ['exception' => $e]);
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = NostoHelperIframe::getUrl(
                $this->nostoIframeMetaBuilder->build($store),
                null, // account creation failed, so we have none.
                $this->nostoCurrentUserBuilder->build(),
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CREATE,
                ]
            );
        }

        return $this->result->setData($response);
    }
}
