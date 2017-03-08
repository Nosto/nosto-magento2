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

namespace Nosto\Tagging\Controller\Oauth;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Sdk\NostoMessage;
use Nosto\Sdk\NostoServiceAccount;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Model\Meta\Oauth\Builder;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    private $_logger;
    private $_backendUrlBuilder;
    private $_accountHelper;
    private $_oauthMetaBuilder;
    private $_accountService;
    private $_storeManager;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $backendUrlBuilder
     * @param Account $accountHelper
     * @param Builder $oauthMetaBuilder
     * @param NostoServiceAccount $accountService
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrlBuilder,
        Account $accountHelper,
        Builder $oauthMetaBuilder,
        NostoServiceAccount $accountService
    ) {
        parent::__construct($context);

        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_backendUrlBuilder = $backendUrlBuilder;
        $this->_accountHelper = $accountHelper;
        $this->_oauthMetaBuilder = $oauthMetaBuilder;
        $this->_accountService = $accountService;
    }

    /**
     * Handles the redirect from Nosto oauth2 authorization server when an
     * existing account is connected to a store.
     * This is handled in the front end as the oauth2 server validates the
     * "return_url" sent in the first step of the authorization cycle, and
     * requires it to be from the same domain that the account is configured
     * for and only redirects to that domain.
     *
     * @return void
     */
    public function execute()
    {
        $request = $this->getRequest();
        /** @var Store $store */
        $store = $this->_storeManager->getStore();
        if (($authCode = $request->getParam('code')) !== null) {
            try {
                $this->connectAccount($authCode, $store);
                $params = [
                    'message_type' => NostoMessage::TYPE_SUCCESS,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'store' => (int)$store->getId(),
                ];
            } catch (\Exception $e) {
                $this->_logger->error($e, ['exception' => $e]);
                $params = [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'store' => (int)$store->getId(),
                ];
            }
            $this->redirectBackend('nosto/account/proxy', $params);
        } elseif (($error = $request->getParam('error')) !== null) {
            $logMsg = $error;
            if (($reason = $request->getParam('error_reason')) !== null) {
                $logMsg .= ' - ' . $reason;
            }
            if (($desc = $request->getParam('error_description')) !== null) {
                $logMsg .= ' - ' . $desc;
            }
            $this->_logger->error($logMsg);
            $this->redirectBackend(
                'nosto/account/proxy',
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'message_text' => $desc,
                    'store' => (int)$store->getId(),
                ]
            );
        } else {
            // todo
            /** @var \Magento\Framework\App\Response\Http $response */
            $response = $this->getResponse();
            $response->setHttpResponseCode(404);
        }
    }

    /**
     * Redirects the user to the Magento backend.
     *
     * @param string $path the backend path to redirect to.
     * @param array $args the url arguments.
     *
     * @return \Magento\Framework\App\ResponseInterface the response.
     */
    private function redirectBackend($path, $args = [])
    {
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();
        $response->setRedirect($this->_backendUrlBuilder->getUrl($path, $args));
        return $response;
    }

    /**
     * Tries to connect the Nosto account and saves the account details to the
     * store config.
     *
     * @param string $authCode the OAuth authorization code by which to get the account details from Nosto.
     * @param Store $store the store the account is connect for.
     * @throws \Exception if the connection fails.
     */
    protected function connectAccount($authCode, $store)
    {
        $oldAccount = $this->_accountHelper->findAccount($store);
        $meta = $this->_oauthMetaBuilder->build($store, $oldAccount);
        $newAccount = $this->_accountService->sync($meta, $authCode);

        // If we are updating an existing account,
        // double check that we got the same account back from Nosto.
        if (!is_null($oldAccount) && !$newAccount->equals($oldAccount)) {
            throw new InputMismatchException(__('Failed to synchronise Nosto account details, account mismatch.'));
        }

        if (!$this->_accountHelper->saveAccount($newAccount, $store)) {
            throw new CouldNotSaveException(__('Failed to save Nosto account.'));
        }

        // todo
//        $this->_accountHelper->updateCurrencyExchangeRates($newAccount, $store);
//        $this->_accountHelper->updateAccount($newAccount, $store);
    }
}
