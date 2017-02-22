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

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\Store;

/**
 * Url helper class for common URL related tasks.
 */
class Url extends AbstractHelper
{
    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var ProductCollectionFactory auto generated product collection factory.
     */
    protected $_productCollectionFactory;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var CategoryCollectionFactory auto generated category collection factory.
     */
    protected $_categoryCollectionFactory;

    /**
     * @var Visibility product visibility.
     */
    protected $_productVisibility;

    /**
     * @var \Magento\Framework\Url frontend URL builder.
     */
    protected $_urlBuilder;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param ProductCollectionFactory $productCollectionFactory auto generated product collection factory.
     * @param CategoryCollectionFactory $categoryCollectionFactory auto generated category collection factory.
     * @param Visibility $productVisibility product visibility.
     * @param \Magento\Framework\Url $urlBuilder frontend URL builder.
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        ProductCollectionFactory $productCollectionFactory,
        /** @noinspection PhpUndefinedClassInspection */
        CategoryCollectionFactory $categoryCollectionFactory,
        Visibility $productVisibility,
        \Magento\Framework\Url $urlBuilder
    ) {
        parent::__construct($context);

        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_productVisibility = $productVisibility;
        $this->_urlBuilder = $urlBuilder;
    }

    /**
     * Gets the absolute preview URL to a given store's product page.
     * The product is the first one found in the database for the store.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     *
     * @return string the url.
     */
    public function getPreviewUrlProduct(Store $store)
    {
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @var \Magento\Catalog\Model\Resource\Product\Collection $collection */
        $collection = $this->_productCollectionFactory->create();
        $collection->addStoreFilter($store->getId());
        $collection->setVisibility($this->_productVisibility->getVisibleInSiteIds());
        $collection->addAttributeToFilter('status', ['eq' => '1']);
        $collection->setCurPage(1);
        $collection->setPageSize(1);
        $collection->load();

        $url = '';
        foreach ($collection->getItems() as $product) {
            /** @var \Magento\Catalog\Model\Product $product */
            $url = $product->getUrlInStore(
                [
                    '_nosid' => true,
                    '_scope_to_url' => true,
                    '_scope' => $store->getCode(),
                ]
            );
            $url = $this->addNostoDebugParamToUrl($url);
        }

        return $url;
    }

    /**
     * Gets the absolute preview URL to a given store's category page.
     * The category is the first one found in the database for the store.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     *
     * @return string the url.
     */
    public function getPreviewUrlCategory(Store $store)
    {
        $rootCatId = (int)$store->getRootCategoryId();
        /** @noinspection PhpUndefinedNamespaceInspection */
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \Magento\Catalog\Model\Resource\Category\Collection $collection */
        $collection = $this->_categoryCollectionFactory->create();
        $collection->addAttributeToFilter('is_active', ['eq' => 1]);
        $collection->addAttributeToFilter('path', ['like' => "1/$rootCatId/%"]);
        $collection->setCurPage(1);
        $collection->setPageSize(1);
        $collection->load();

        foreach ($collection->getItems() as $category) {
            /** @var \Magento\Catalog\Model\Category $category */
            $url = $category->getUrl();
            $url = $this->replaceQueryParamsInUrl(
                array('___store' => $store->getCode()),
                $url
            );
            return $this->addNostoDebugParamToUrl($url);
        }

        return '';
    }

    /**
     * Gets the absolute preview URL to the given store's search page.
     * The search query in the URL is "q=nosto".
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     *
     * @return string the url.
     */
    public function getPreviewUrlSearch(Store $store)
    {
        $url = $this->_urlBuilder->getUrl(
            'catalogsearch/result',
            array(
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            )
        );
        $url = $this->replaceQueryParamsInUrl(array('q' => 'nosto'), $url);
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Gets the absolute preview URL to the given store's cart page.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     *
     * @return string the url.
     */
    public function getPreviewUrlCart(Store $store)
    {
        $url = $this->_urlBuilder->getUrl(
            'checkout/cart',
            array(
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            )
        );
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Gets the absolute preview URL to the given store's front page.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param Store $store the store to get the url for.
     *
     * @return string the url.
     */
    public function getPreviewUrlFront(Store $store)
    {
        $url = $this->_urlBuilder->getUrl(
            '',
            array(
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            )
        );
        return $this->addNostoDebugParamToUrl($url);
    }

    /**
     * Replaces or adds a query parameters to a url.
     *
     * @param array $params the query params to replace.
     * @param string $url the url.
     * @return string the updated url.
     */
    public function replaceQueryParamsInUrl(array $params, $url)
    {
        return \Nosto\Sdk\NostoHttpRequest::replaceQueryParamsInUrl($params, $url);
    }

    /**
     * Adds the `nostodebug` parameter to a url.
     *
     * @param string $url the url.
     * @return string the updated url.
     */
    public function addNostoDebugParamToUrl($url)
    {
        return \Nosto\Sdk\NostoHttpRequest::replaceQueryParamInUrl(
            'nostodebug',
            'true',
            $url
        );
    }
}
