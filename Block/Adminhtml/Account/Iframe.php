<?php

namespace Nosto\Tagging\Block\Adminhtml\Account;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\NotFoundException;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Iframe block for displaying the Nosto account management iframe.
 * This iframe is used to setup and manage your Nosto accounts on a store basis
 * in Magento.
 */
class Iframe extends Template
{
    /**
     * Default iframe origin regexp for validating window.postMessage() calls.
     */
    const DEFAULT_IFRAME_ORIGIN_REGEXP = '(https:\/\/(.*)\.hub\.nosto\.com)|(https:\/\/my\.nosto\.com)';

    /**
     * @inheritdoc
     */
    protected $_template = 'iframe.phtml';

    /**
     * @var Account account helper.
     */
    protected $_accountHelper;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param Account $accountHelper the account helper.
	 * @param Session $backendAuthSession
     * @param array   $data optional data.
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
		Session $backendAuthSession,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->_accountHelper = $accountHelper;
		$this->_backendAuthSession = $backendAuthSession;
    }

    /**
     * Gets the iframe url for the account settings page from Nosto.
     * If there is an account for the current store and the admin user can be
     * logged in to that account using SSO, the url will be for the account
     * management. In other cases, the url will be that of the install screen
     * where a new Nosto account can be created.
     *
     * @return string the iframe url or empty string if it cannot be created.
     */
    public function getIframeUrl()
    {
        $params = array();

		// Pass any error/success messages we might have to the iframe.
		// These can be available when getting redirect back from the OAuth
		// front controller after connecting a Nosto account to a store.
		$nostoMessage = $this->_backendAuthSession->getData('nosto_message');
		if (is_array($nostoMessage) && !empty($nostoMessage)) {
			foreach ($nostoMessage as $key => $value) {
				if (is_string($key) && !empty($value)) {
					$params[$key] = $value;
				}
			}
			$this->_backendAuthSession->setData('nosto_message', null);
		}

        $store = $this->getSelectedStore();
        $account = $this->_accountHelper->findAccount($store);
        return $this->_accountHelper->getIframeUrl($store, $account, $params);
    }

    /**
     * Returns the config for the Nosto iframe JS component.
     * This config can be converted into JSON in the view file.
     *
     * @return array the config.
     */
    public function getIframeConfig()
    {
        $get = [
            'store' => $this->getSelectedStore()->getId(),
            'isAjax' => true
        ];
        return [
            'iframe_handler' => [
                'origin' => $this->getIframeOrigin(),
                'xhrParams' => [
                    'form_key' => $this->formKey->getFormKey()
                ],
                'urls' => [
                    'createAccount' => $this->getUrl('*/*/create', $get),
                    'connectAccount' => $this->getUrl('*/*/connect', $get),
                    'syncAccount' => $this->getUrl('*/*/sync', $get),
                    'deleteAccount' => $this->getUrl('*/*/delete', $get)
                ]
            ]
        ];
    }

    /**
     * Returns the valid origin url regexp from where the iframe should accept
     * postMessage calls.
     * This is configurable to support different origins based on $_ENV.
     *
     * @return string the origin url regexp.
     */
    public function getIframeOrigin()
    {
        return (isset($_ENV['NOSTO_IFRAME_ORIGIN_REGEXP']))
            ? $_ENV['NOSTO_IFRAME_ORIGIN_REGEXP']
            : self::DEFAULT_IFRAME_ORIGIN_REGEXP;
    }

    /**
     * Returns the currently selected store.
     * Nosto can only be configured on a store basis, and if we cannot find a
     * store, an exception is thrown.
     *
     * @return Store the store.
     *
     * @throws NotFoundException store not found.
     */
    public function getSelectedStore()
    {
        if ($this->_storeManager->isSingleStoreMode()) {
            return $this->_storeManager->getStore(true);
        } elseif (($storeId = $this->_request->getParam('store'))) {
            return $this->_storeManager->getStore($storeId);
        } else {
            throw new NotFoundException(__('Store not found.'));
        }
    }
}
