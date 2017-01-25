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

namespace Nosto\Tagging\Block\Adminhtml\Account;

use Magento\Backend\Block\Template as BlockTemplate;
use Magento\Backend\Block\Template\Context as BlockContext;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\NotFoundException;
use Magento\Store\Api\Data\StoreInterface;
use Nosto;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\Meta\Account\Sso\Builder as NostoSsoBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use NostoHelperIframe;

/**
 * Iframe block for displaying the Nosto account management iframe.
 * This iframe is used to setup and manage your Nosto accounts on a store basis
 * in Magento.
 */
class Iframe extends BlockTemplate
{
    /**
     * Default iframe origin regexp for validating window.postMessage() calls.
     */
    const DEFAULT_IFRAME_ORIGIN_REGEXP = '(https:\/\/(.*)\.hub\.nosto\.com)|(https:\/\/my\.nosto\.com)';
    protected $nostoHelperAccount;
    private $backendAuthSession;
    private $nostoSsoBuilder;
    private $nostoIframeMetaBuilder;
    private $nostoCurrentUserBuilder;

    /**
     * Constructor.
     *
     * @param BlockContext $context the context.
     * @param NostoHelperAccount $nostoHelperAccount the account helper.
     * @param Session $backendAuthSession
     * @param NostoSsoBuilder $nostoSsoBuilder
     * @param NostoIframeMetaBuilder $iframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param array $data
     */
    public function __construct(
        BlockContext $context,
        NostoHelperAccount $nostoHelperAccount,
        Session $backendAuthSession,
        NostoSsoBuilder $nostoSsoBuilder,
        NostoIframeMetaBuilder $iframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->backendAuthSession = $backendAuthSession;
        $this->nostoSsoBuilder = $nostoSsoBuilder;
        $this->nostoIframeMetaBuilder = $iframeMetaBuilder;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
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
        $params = [];
        // Pass any error/success messages we might have to the iframe.
        // These can be available when getting redirect back from the OAuth
        // front controller after connecting a Nosto account to a store.
        $nostoMessage = $this->backendAuthSession->getData('nosto_message');
        if (is_array($nostoMessage) && !empty($nostoMessage)) {
            foreach ($nostoMessage as $key => $value) {
                if (is_string($key) && !empty($value)) {
                    $params[$key] = $value;
                }
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $this->backendAuthSession->setData('nosto_message', null);
        }

        $store = $this->getSelectedStore();
        $account = $this->nostoHelperAccount->findAccount($store);
        return NostoHelperIframe::getUrl(
            $this->nostoIframeMetaBuilder->build($store),
            $account,
            $this->nostoCurrentUserBuilder->build(),
            $params
        );
    }

    /**
     * Returns the currently selected store.
     * Nosto can only be configured on a store basis, and if we cannot find a
     * store, an exception is thrown.
     *
     * @return StoreInterface the store.
     *
     * @throws NotFoundException store not found.
     */
    public function getSelectedStore()
    {
        $store = null;
        if ($this->_storeManager->isSingleStoreMode()) {
            $store = $this->_storeManager->getStore(true);
        } elseif (($storeId = $this->_request->getParam('store'))) {
            $store = $this->_storeManager->getStore($storeId);
        } elseif (($this->_storeManager->getStore())) {
            $store = $this->_storeManager->getStore();
        } else {
            throw new NotFoundException(__('Store not found.'));
        }

        return $store;
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
        return Nosto::getEnvVariable('NOSTO_IFRAME_ORIGIN_REGEXP',
            self::DEFAULT_IFRAME_ORIGIN_REGEXP);
    }
}
