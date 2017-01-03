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
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Oauth\Builder as NostoOauthBuilder;
use NostoMessage;
use NostoOperationOauthSync;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    private $logger;
    private $backendUrlBuilder;
    private $nostoHelperAccount;
    private $oauthMetaBuilder;
    private $storeManager;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $backendUrlBuilder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoOauthBuilder $oauthMetaBuilder
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrlBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoOauthBuilder $oauthMetaBuilder
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->backendUrlBuilder = $backendUrlBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->oauthMetaBuilder = $oauthMetaBuilder;
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
        $store = $this->storeManager->getStore();
        if (($authCode = $request->getParam('code')) !== null) {
            try {
                $this->connectAccount($authCode, $store);
                $params = [
                    'message_type' => NostoMessage::TYPE_SUCCESS,
                    'message_code' => NostoMessage::CODE_ACCOUNT_CONNECT,
                    'store' => (int)$store->getId(),
                ];
            } catch (\Exception $e) {
                $this->logger->error($e, ['exception' => $e]);
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
            $this->logger->error($logMsg);
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
     * Tries to connect the Nosto account and saves the account details to the
     * store config.
     *
     * @param string $authCode the OAuth authorization code by which to get the account details from Nosto.
     * @param Store $store the store the account is connect for.
     * @throws \Exception if the connection fails.
     */
    protected function connectAccount($authCode, $store)
    {
        $oldAccount = $this->nostoHelperAccount->findAccount($store);
        $meta = $this->oauthMetaBuilder->build($store, $oldAccount);
        $operation = new NostoOperationOauthSync($meta);
        $newAccount = $operation->exchange($authCode);

        // If we are updating an existing account,
        // double check that we got the same account back from Nosto.
        if (!is_null($oldAccount) && $newAccount->getName() !== $oldAccount->getName()) {
            throw new InputMismatchException(__('Failed to synchronise Nosto account details, account mismatch.'));
        }

        if (!$this->nostoHelperAccount->saveAccount($newAccount, $store)) {
            throw new CouldNotSaveException(__('Failed to save Nosto account.'));
        }

        // todo
//        $this->nostoHelperAccount->updateCurrencyExchangeRates($newAccount, $store);
//        $this->nostoHelperAccount->updateAccount($newAccount, $store);
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
        $response->setRedirect($this->backendUrlBuilder->getUrl($path, $args));
        return $response;
    }
}
