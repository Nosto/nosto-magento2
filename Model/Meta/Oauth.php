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

namespace Nosto\Tagging\Model\Meta;

class Oauth implements \NostoOauthClientMetaInterface
{
    /**
     * @var array The scopes for the OAuth2 request.
     */
    protected $_scopes = array();

    /**
     * @var string the url where the oauth2 server should redirect after
     * authorization is done.
     */
    protected $_redirectUrl;

    /**
     * @var \NostoLanguageCode the 2-letter ISO code (ISO 639-1) for localization
     * on oauth2 server.
     */
    protected $_language;

    /**
     * @var \NostoAccount|null account if OAuth is to sync details.
     */
    protected $_account;

    /**
     * @inheritdoc
     */
    public function getClientId()
    {
        return 'magento';
    }

    /**
     * @inheritdoc
     */
    public function getClientSecret()
    {
        return 'magento';
    }

    /**
     * @inheritdoc
     */
    public function getScopes()
    {
        return $this->_scopes;
    }

    /**
     * @inheritdoc
     */
    public function setScopes(array $scopes)
    {
        $this->_scopes = $scopes;
    }

    /**
     * @inheritdoc
     */
    public function getRedirectUrl()
    {
        return $this->_redirectUrl;
    }

    /**
     * @inheritdoc
     */
    public function setRedirectUrl($redirectUrl)
    {
        $this->_redirectUrl = $redirectUrl;
    }

    /**
     * @inheritdoc
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * @inheritdoc
     */
    public function setLanguage(\NostoLanguageCode $language)
    {
        $this->_language = $language;
    }

    /**
     * @inheritdoc
     */
    public function getAccount()
    {
        return $this->_account;
    }

    /**
     * @inheritdoc
     */
    public function setAccount(\NostoAccount $account)
    {
        $this->_account = $account;
    }
}
