<?php

namespace Magento\Store\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreExtensionInterface;

interface StoreInterface extends ExtensibleDataInterface
{
    public function getId();
    public function setId($id);
    public function getCode();
    public function setCode($code);
    public function getName();
    public function setName($name);
    public function getWebsiteId();
    public function setWebsiteId($websiteId);
    public function getStoreGroupId();
    public function setStoreGroupId($storeGroupId);
    public function getExtensionAttributes();
    public function setExtensionAttributes(StoreExtensionInterface $extensionAttributes);
    public function getConfig($path);
    public function getAvailableCurrencyCodes($skipBaseNotAllowed = false);
    public function getCurrentCurrencyCode();
    public function resetConfig();
    public function getRootCategoryId();
    public function getBaseCurrencyCode();
    public function getWebsite();
    public function getGroup();
    public function getBaseUrl($type = UrlInterface::URL_TYPE_LINK, $secure = null);
}