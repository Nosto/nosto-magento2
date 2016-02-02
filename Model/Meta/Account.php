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

class Account implements \NostoAccountMetaInterface
{
    /**
     * @var string the store name.
     */
    protected $_title;

    /**
     * @var string the account name.
     */
    protected $_name;

    /**
     * @var string the store front end url.
     */
    protected $_frontPageUrl;

    /**
     * @var \NostoCurrencyCode the store currency ISO (ISO 4217) code.
     */
    protected $_currency;

    /**
     * @var \NostoLanguageCode the store language ISO (ISO 639-1) code.
     */
    protected $_language;

    /**
     * @var \NostoLanguageCode the owner language ISO (ISO 639-1) code.
     */
    protected $_ownerLanguage;

    /**
     * @var \Nosto\Tagging\Model\Meta\Account\Owner the account owner meta model.
     */
    protected $_owner;

    /**
     * @var \Nosto_Tagging_Model_Meta_Account_Billing the billing meta model.
     */
    protected $_billing;

    /**
     * @var \NostoCurrency[] list of supported currencies by the store.
     */
    protected $_currencies = array();

    /**
     * @var string the default price variation ID if using multiple currencies.
     */
    protected $_defaultPriceVariationId;

    /**
     * @var bool if the store uses exchange rates to manage multiple currencies.
     */
    protected $_useCurrencyExchangeRates = false;

    /**
     * @var string the API token used to identify an account creation.
     */
    protected $_signUpApiToken = 'YBDKYwSqTCzSsU8Bwbg4im2pkHMcgTy9cCX7vevjJwON1UISJIwXOLMM0a8nZY7h';

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @inheritdoc
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getPlatform()
    {
        return 'magento';
    }

    /**
     * @inheritdoc
     */
    public function getFrontPageUrl()
    {
        return $this->_frontPageUrl;
    }

    /**
     * @inheritdoc
     */
    public function setFrontPageUrl($frontPageUrl)
    {
        $this->_frontPageUrl = $frontPageUrl;
    }

    /**
     * @inheritdoc
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * @inheritdoc
     */
    public function setCurrency(\NostoCurrencyCode $currency)
    {
        $this->_currency = $currency;
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
    public function getOwnerLanguage()
    {
        return $this->_ownerLanguage;
    }

    /**
     * @inheritdoc
     */
    public function setOwnerLanguage(\NostoLanguageCode $ownerLanguage)
    {
        $this->_ownerLanguage = $ownerLanguage;
    }

    /**
     * @inheritdoc
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    /**
     * @inheritdoc
     */
    public function setOwner(\NostoAccountMetaOwnerInterface $owner)
    {
        $this->_owner = $owner;
    }

    /**
     * @inheritdoc
     */
    public function getBillingDetails()
    {
        return $this->_billing;
    }

    /**
     * @inheritdoc
     */
    public function getCurrencies()
    {
        return $this->_currencies;
    }

    /**
     * @inheritdoc
     */
    public function setCurrencies(array $currencies)
    {
        $this->_currencies = $currencies;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultPriceVariationId()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getUseCurrencyExchangeRates()
    {
        return $this->_useCurrencyExchangeRates;
    }

    /**
     * @inheritdoc
     */
    public function setUseCurrencyExchangeRates($useCurrencyExchangeRates)
    {
        $this->_useCurrencyExchangeRates = $useCurrencyExchangeRates;
    }

    /**
     * @inheritdoc
     */
    public function getSignUpApiToken()
    {
        return $this->_signUpApiToken;
    }

    /**
     * @inheritdoc
     */
    public function getPartnerCode()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function setBilling(\NostoAccountMetaBillingInterface $billing)
    {
        $this->_billing = $billing;
    }

    /**
     * @inheritdoc
     */
    public function getUseMultiVariants()
    {
        return false;
    }
}
