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
     * @var \Nosto_Tagging_Model_Meta_Account_Owner the account owner meta model.
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
     * The shops name for which the account is to be created for.
     *
     * @return string the name.
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * The name of the account to create.
     * This has to follow the pattern of
     * "[platform name]-[8 character lowercase alpha numeric string]".
     *
     * @return string the account name.
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * The name of the platform the account is used on.
     * A list of valid platform names is issued by Nosto.
     *
     * @return string the platform names.
     */
    public function getPlatform()
    {
        return 'magento'; // todo: change to "magento2"
    }

    /**
     * Absolute url to the front page of the shop for which the account is
     * created for.
     *
     * @return string the url.
     */
    public function getFrontPageUrl()
    {
        return $this->_frontPageUrl;
    }

    /**
     * @param string $frontPageUrl
     */
    public function setFrontPageUrl($frontPageUrl)
    {
        $this->_frontPageUrl = $frontPageUrl;
    }

    /**
     * The 3-letter ISO code (ISO 4217) for the currency used by the shop for
     * which the account is created for.
     *
     * @return \NostoCurrencyCode the currency code.
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * @param \NostoCurrencyCode $currency
     */
    public function setCurrency(\NostoCurrencyCode $currency)
    {
        $this->_currency = $currency;
    }

    /**
     * The 2-letter ISO code (ISO 639-1) for the language used by the shop for
     * which the account is created for.
     *
     * @return \NostoLanguageCode the language code.
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * @param \NostoLanguageCode $language
     */
    public function setLanguage(\NostoLanguageCode $language)
    {
        $this->_language = $language;
    }

    /**
     * The 2-letter ISO code (ISO 639-1) for the language of the account owner
     * who is creating the account.
     *
     * @return \NostoLanguageCode the language code.
     */
    public function getOwnerLanguage()
    {
        return $this->_ownerLanguage;
    }

    /**
     * @param \NostoLanguageCode $ownerLanguage
     */
    public function setOwnerLanguage(\NostoLanguageCode $ownerLanguage)
    {
        $this->_ownerLanguage = $ownerLanguage;
    }

    /**
     * Meta data model for the account owner who is creating the account.
     *
     * @return \NostoAccountMetaOwnerInterface the meta data model.
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    // todo

    /**
     * @param \NostoAccountMetaOwnerInterface $owner
     */
    public function setOwner(\NostoAccountMetaOwnerInterface $owner)
    {
        $this->_owner = $owner;
    }

    /**
     * Meta data model for the account billing details.
     *
     * @return \NostoAccountMetaBillingInterface the meta data model.
     */
    public function getBillingDetails()
    {
        return $this->_billing;
    }

    /**
     * Returns a list of currency objects supported by the store the account is
     * to be created for.
     *
     * @return \NostoCurrency[] the currencies.
     */
    public function getCurrencies()
    {
        return $this->_currencies;
    }

    /**
     * @param \NostoCurrency[] $currencies
     */
    public function setCurrencies(array $currencies)
    {
        $this->_currencies = $currencies;
    }

    /**
     * Returns the default price variation ID if store is using multiple
     * currencies.
     * This ID identifies the price that products are specified in and can
     * be set to the currency ISO 639-1 code
     *
     * @return string|null the currency ID or null if not set.
     */
    public function getDefaultPriceVariationId()
    {
        return $this->_defaultPriceVariationId;
    }

    /**
     * @param string $defaultPriceVariationId
     */
    public function setDefaultPriceVariationId($defaultPriceVariationId)
    {
        $this->_defaultPriceVariationId = $defaultPriceVariationId;
    }

    /**
     * Returns if exchange rates are used to handle multi-currency setups.
     * It is also possible to handle multi-currency setups using variation
     * tagging on the product pages, i.e. in addition to the product base price,
     * you also tag all price variations.
     *
     * @return bool if the rates are used.
     */
    public function getUseCurrencyExchangeRates()
    {
        return $this->_useCurrencyExchangeRates;
    }

    /**
     * @param boolean $useCurrencyExchangeRates
     */
    public function setUseCurrencyExchangeRates($useCurrencyExchangeRates)
    {
        $this->_useCurrencyExchangeRates = $useCurrencyExchangeRates;
    }

    /**
     * The API token used to identify an account creation.
     * This token is platform specific and issued by Nosto.
     *
     * @return string the API token.
     */
    public function getSignUpApiToken()
    {
        return $this->_signUpApiToken;
    }

    /**
     * Optional partner code for Nosto partners.
     * The code is issued by Nosto to partners only.
     *
     * @return string|null the partner code or null if none exist.
     */
    public function getPartnerCode()
    {
        return null;
    }

    /**
     * @param \NostoAccountMetaBillingInterface $billing
     */
    public function setBilling(\NostoAccountMetaBillingInterface $billing)
    {
        $this->_billing = $billing;
    }

    /**
     * Returns if the multi variant approach should be used for handling
     * multiple currencies or in pricing. Please note that only tells if the
     * setting is active. This will not take account whether there are variants
     * configured or not.
     *
     * @return boolean if multi variants are used
     */
    public function getUseMultiVariants()
    {
        return false;
    }
}
