<?php

namespace Nosto\Tagging\Model\Meta\Account;

class Iframe implements \NostoAccountMetaIframeInterface
{
    /**
     * @var string unique ID that identifies the Magento installation.
     */
    protected $_uniqueId;

    /**
     * @var \NostoLanguageCode the language code for oauth server locale.
     */
    protected $_language;

    /**
     * @var \NostoLanguageCode the language code for the store view scope.
     */
    protected $_shopLanguage;

    /**
     * @var string the name of the store Nosto is installed in or about to be installed.
     */
    protected $_shopName;

    /**
     * @var string the Magento version number.
     */
    protected $_versionPlatform;

    /**
     * @var string the Nosto_Tagging version number.
     */
    protected $_versionModule;

    /**
     * @var string preview url for the product page in the active store scope.
     */
    protected $_previewUrlProduct;

    /**
     * @var string preview url for the category page in the active store scope.
     */
    protected $_previewUrlCategory;

    /**
     * @var string preview url for the search page in the active store scope.
     */
    protected $_previewUrlSearch;

    /**
     * @var string preview url for the cart page in the active store scope.
     */
    protected $_previewUrlCart;

    /**
     * @var string preview url for the front page in the active store scope.
     */
    protected $_previewUrlFront;

    /**
     * Unique identifier for the e-commerce installation.
     * This identifier is used to link accounts together that are created on
     * the same installation.
     *
     * @return string the identifier.
     */
    public function getUniqueId()
    {
        return $this->_uniqueId;
    }

    /**
     * The 2-letter ISO code (ISO 639-1) for the language of the user who is
     * loading the config iframe.
     *
     * @return \NostoLanguageCode the language code.
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * The 2-letter ISO code (ISO 639-1) for the language of the shop the
     * account belongs to.
     *
     * @return \NostoLanguageCode the language code.
     */
    public function getShopLanguage()
    {
        return $this->_shopLanguage;
    }

    /**
     * Returns the name of the shop context where Nosto is installed or about to be installed in.
     *
     * @return string the name.
     */
    public function getShopName()
    {
        return $this->_shopName;
    }

    /**
     * The version number of the platform the e-commerce installation is
     * running on.
     *
     * @return string the platform version.
     */
    public function getVersionPlatform()
    {
        // todo
        //return Mage::getVersion();
        return '0.0.0';
    }

    /**
     * The version number of the Nosto module/extension running on the
     * e-commerce installation.
     *
     * @return string the module version.
     */
    public function getVersionModule()
    {
        // todo
        // Path is hard-coded to be like in "etc/config.xml".
        //return (string)Mage::getConfig()
        //    ->getNode('modules/Nosto_Tagging/version');
        return '0.0.0';
    }

    /**
     * An absolute URL for any product page in the shop the account is linked
     * to, with the nostodebug GET parameter enabled.
     * e.g. http://myshop.com/products/product123?nostodebug=true
     * This is used in the config iframe to allow the user to quickly preview
     * the recommendations on the given page.
     *
     * @return string the url.
     */
    public function getPreviewUrlProduct()
    {
        return $this->_previewUrlProduct;
    }

    /**
     * An absolute URL for any category page in the shop the account is linked
     * to, with the nostodebug GET parameter enabled.
     * e.g. http://myshop.com/products/category123?nostodebug=true
     * This is used in the config iframe to allow the user to quickly preview
     * the recommendations on the given page.
     *
     * @return string the url.
     */
    public function getPreviewUrlCategory()
    {
        return $this->_previewUrlCategory;
    }

    /**
     * An absolute URL for the search page in the shop the account is linked
     * to, with the nostodebug GET parameter enabled.
     * e.g. http://myshop.com/search?query=red?nostodebug=true
     * This is used in the config iframe to allow the user to quickly preview
     * the recommendations on the given page.
     *
     * @return string the url.
     */
    public function getPreviewUrlSearch()
    {
        return $this->_previewUrlSearch;
    }

    /**
     * An absolute URL for the shopping cart page in the shop the account is
     * linked to, with the nostodebug GET parameter enabled.
     * e.g. http://myshop.com/cart?nostodebug=true
     * This is used in the config iframe to allow the user to quickly preview
     * the recommendations on the given page.
     *
     * @return string the url.
     */
    public function getPreviewUrlCart()
    {
        return $this->_previewUrlCart;
    }

    /**
     * An absolute URL for the front page in the shop the account is linked to,
     * with the nostodebug GET parameter enabled.
     * e.g. http://myshop.com?nostodebug=true
     * This is used in the config iframe to allow the user to quickly preview
     * the recommendations on the given page.
     *
     * @return string the url.
     */
    public function getPreviewUrlFront()
    {
        return $this->_previewUrlFront;
    }

    // todo

    public function setUniqueId($uniqueId)
    {
        $this->_uniqueId = $uniqueId;
    }

    public function setLanguage(\NostoLanguageCode $language)
    {
        $this->_language = $language;
    }

    public function setShopLanguage(\NostoLanguageCode $shopLanguage)
    {
        $this->_shopLanguage = $shopLanguage;
    }

    public function setShopName($shopName)
    {
        $this->_shopName = $shopName;
    }

    public function setVersionPlatform($platformVersion)
    {
        $this->_versionPlatform = $platformVersion;
    }

    public function setVersionModule($moduleVersion)
    {
        $this->_versionModule = $moduleVersion;
    }

    public function setPreviewUrlProduct($productPreviewUrl)
    {
        $this->_previewUrlProduct = $productPreviewUrl;
    }

    public function setPreviewUrlCategory($categoryPreviewUrl)
    {
        $this->_previewUrlCategory = $categoryPreviewUrl;
    }

    public function setPreviewUrlSearch($searchPreviewUrl)
    {
        $this->_previewUrlSearch = $searchPreviewUrl;
    }

    public function setPreviewUrlCart($cartPreviewUrl)
    {
        $this->_previewUrlCart = $cartPreviewUrl;
    }

    public function setPreviewUrlFront($frontPreviewUrl)
    {
        $this->_previewUrlFront = $frontPreviewUrl;
    }
}
