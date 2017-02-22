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

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Nosto\Sdk\NostoAccount;
use Nosto\Sdk\NostoException;
use Nosto\Sdk\NostoServiceAccount;
use Nosto\Tagging\Helper\Data as NostoHelper;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as IframeMetaBuilder;
use Nosto\Tagging\Model\Meta\Account\Sso\Builder as SsoMetaBuilder;


/**
 * Account helper class for common tasks related to Nosto accounts.
 * Everything related to saving/updating/deleting accounts happens in here.
 */
class Account extends AbstractHelper
{
    /**
     * Path to store config nosto account name.
     */
    const XML_PATH_ACCOUNT = 'nosto_tagging/settings/account';

    /**
     * Path to store config nosto account tokens.
     */
    const XML_PATH_TOKENS = 'nosto_tagging/settings/tokens';

    /**
     * Platform UI version
     */
    const IFRAME_VERSION = 0;

    /**
     * @var SsoMetaBuilder the builder for sso meta models.
     */
    protected $_ssoMetaBuilder;

    /**
     * @var IframeMetaBuilder the builder for iframe meta models.
     */
    protected $_iframeMetaBuilder;

    /**
     * @var \Nosto\Sdk\NostoHelperIframe the Nosto SDK iframe helper.
     */
    protected $_iframeHelper;

    /**
     * @var WriterInterface the app config writer.
     */
    protected $_config;

    /**
     * @var ModuleManager
     */
    protected $_moduleManager;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param SsoMetaBuilder $ssoMetaBuilder the builder for sso meta models.
     * @param IframeMetaBuilder $iframeMetaBuilder the builder for iframe meta models.
     * @param \Nosto\Sdk\NostoHelperIframe $iframeHelper
     * @param WriterInterface $appConfig the app config writer.
     */
    public function __construct(
        Context $context,
        SsoMetaBuilder $ssoMetaBuilder,
        IframeMetaBuilder $iframeMetaBuilder,
        \Nosto\Sdk\NostoHelperIframe $iframeHelper,
        WriterInterface $appConfig
    ) {
        parent::__construct($context);

        $this->_ssoMetaBuilder = $ssoMetaBuilder;
        $this->_iframeMetaBuilder = $iframeMetaBuilder;
        $this->_iframeHelper = $iframeHelper;
        $this->_config = $appConfig;
        $this->_moduleManager = $context->getModuleManager();
    }

    /**
     * Returns the account with associated api tokens for the store.
     *
     * @param StoreInterface $store the store.
     *
     * @return NostoAccount|null the account or null if not found.
     */
    public function findAccount(StoreInterface $store)
    {
        /** @var Store $store */
        $accountName = $store->getConfig(self::XML_PATH_ACCOUNT);

        if (!empty($accountName)) {
            $account = new NostoAccount($accountName);
            $tokens = json_decode(
                $store->getConfig(self::XML_PATH_TOKENS),
                true
            );
            if (is_array($tokens) && !empty($tokens)) {
                foreach ($tokens as $name => $value) {
                    try {
                        $account->addApiToken(
                            new \Nosto\Sdk\NostoApiToken($name, $value)
                        );
                    } catch (\Nosto\Sdk\NostoInvalidArgumentException $e) {

                    }
                }
            }
            return $account;
        }

        return null;
    }

    /**
     * Saves the account and the associated api tokens for the store.
     *
     * @param \Nosto\Sdk\NostoAccountMetaInterface $account the account to save.
     * @param Store $store the store.
     *
     * @return bool true on success, false otherwise.
     */
    public function saveAccount(\Nosto\Sdk\NostoAccountMetaInterface $account, Store $store)
    {
        if ((int)$store->getId() < 1) {
            return false;
        }

        $tokens = array();
        foreach ($account->getTokens() as $token) {
            $tokens[$token->getName()] = $token->getValue();
        }

        $this->_config->save(
            self::XML_PATH_ACCOUNT,
            $account->getName(),
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $this->_config->save(
            self::XML_PATH_TOKENS,
            json_encode($tokens),
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        $store->resetConfig();

        return true;
    }

    /**
     * Removes an account with associated api tokens for the store.
     *
     * @param NostoAccount $account the account to remove.
     * @param Store $store the store.
     *
     * @return bool true on success, false otherwise.
     */
    public function deleteAccount(NostoAccount $account, Store $store)
    {
        if ((int)$store->getId() < 1) {
            return false;
        }

        $this->_config->delete(
            self::XML_PATH_ACCOUNT,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $this->_config->delete(
            self::XML_PATH_TOKENS,
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );

        try {
            // Notify Nosto that the account was deleted.
            $service = new NostoServiceAccount();
            $service->delete($account);
        } catch (NostoException $e) {
            // Failures are logged but not shown to the user.
            $this->_logger->error($e, ['exception' => $e]);
        }

        $store->resetConfig();

        return true;
    }

    /**
     * Returns the account administration iframe url.
     * If there is no account, the "front page" url will be returned where an
     * account can be created from.
     *
     * @param Store $store the store to get the url for.
     * @param NostoAccount $account the account to get the iframe url for.
     * @param array $params optional extra params for the url.
     *
     * @return string the iframe url.
     */
    public function getIframeUrl(
        Store $store,
        NostoAccount $account = null,
        array $params = []
    ) {
        if (self::IFRAME_VERSION > 0) {
            $params['v'] = self::IFRAME_VERSION;
        }
        return $this->_iframeHelper->getUrl(
            $this->_ssoMetaBuilder->build(),
            $this->_iframeMetaBuilder->build($store),
            $account,
            $params
        );
    }

    /**
     * Checks if Nosto module is enabled and Nosto account is set
     *
     * @param StoreInterface $store
     * @return bool
     */
    public function nostoInstalledAndEnabled(StoreInterface $store) {

        $enabled = false;
        if ($this->_moduleManager->isEnabled(NostoHelper::MODULE_NAME)) {
            if ($this->findAccount($store)) {
                $enabled = true;
            }
        }

        return $enabled;
    }
}
