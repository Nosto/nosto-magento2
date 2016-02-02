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
     * @inheritdoc
     */
    public function getUniqueId()
    {
        return $this->_uniqueId;
    }

    /**
     * @inheritdoc
     */
    public function setUniqueId($uniqueId)
    {
        $this->_uniqueId = $uniqueId;
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
    public function getShopLanguage()
    {
        return $this->_shopLanguage;
    }

    /**
     * @inheritdoc
     */
    public function setShopLanguage(\NostoLanguageCode $shopLanguage)
    {
        $this->_shopLanguage = $shopLanguage;
    }

    /**
     * @inheritdoc
     */
    public function getShopName()
    {
        return $this->_shopName;
    }

    /**
     * @inheritdoc
     */
    public function setShopName($shopName)
    {
        $this->_shopName = $shopName;
    }

    /**
     * @inheritdoc
     */
    public function getVersionPlatform()
    {
        return $this->_versionPlatform;
    }

    /**
     * @inheritdoc
     */
    public function setVersionPlatform($platformVersion)
    {
        $this->_versionPlatform = $platformVersion;
    }

    /**
     * @inheritdoc
     */
    public function getVersionModule()
    {
        return $this->_versionModule;
    }

    /**
     * @inheritdoc
     */
    public function setVersionModule($moduleVersion)
    {
        $this->_versionModule = $moduleVersion;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewUrlProduct()
    {
        return $this->_previewUrlProduct;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewUrlProduct($productPreviewUrl)
    {
        $this->_previewUrlProduct = $productPreviewUrl;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewUrlCategory()
    {
        return $this->_previewUrlCategory;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewUrlCategory($categoryPreviewUrl)
    {
        $this->_previewUrlCategory = $categoryPreviewUrl;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewUrlSearch()
    {
        return $this->_previewUrlSearch;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewUrlSearch($searchPreviewUrl)
    {
        $this->_previewUrlSearch = $searchPreviewUrl;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewUrlCart()
    {
        return $this->_previewUrlCart;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewUrlCart($cartPreviewUrl)
    {
        $this->_previewUrlCart = $cartPreviewUrl;
    }

    /**
     * @inheritdoc
     */
    public function getPreviewUrlFront()
    {
        return $this->_previewUrlFront;
    }

    /**
     * @inheritdoc
     */
    public function setPreviewUrlFront($frontPreviewUrl)
    {
        $this->_previewUrlFront = $frontPreviewUrl;
    }
}
