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
use /** @noinspection PhpUndefinedClassInspection */
    Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use /** @noinspection PhpUndefinedClassInspection */
    Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Url helper class for common URL related tasks.
 */
class Url extends AbstractHelper
{
    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var ProductCollectionFactory auto generated product collection factory.
     */
    protected $productCollectionFactory;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var CategoryCollectionFactory auto generated category collection factory.
     */
    protected $categoryCollectionFactory;

    /**
     * @var Visibility product visibility.
     */
    protected $productVisibility;

    /**
     * @var \Magento\Framework\Url frontend URL builder.
     */
    protected $urlBuilder;

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

        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Gets the absolute preview URL to a given store's product page.
     * The product is the first one found in the database for the store.
     * The preview url includes "nostodebug=true" parameter.
     *
     * @param StoreInterface $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlProduct(StoreInterface $store)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($store->getId());
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
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
     * @param StoreInterface $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlCategory(StoreInterface $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $rootCatId = (int)$store->getRootCategoryId();
        /** @noinspection PhpUndefinedClassInspection */
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        /** @noinspection PhpUndefinedMethodInspection */
        $collection = $this->categoryCollectionFactory->create();
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
     * @param StoreInterface $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlSearch(StoreInterface $store)
    {
        $url = $this->urlBuilder->getUrl(
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
     * @param StoreInterface $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlCart(StoreInterface $store)
    {
        $url = $this->urlBuilder->getUrl(
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
     * @param StoreInterface $store the store to get the url for.
     * @return string the url.
     */
    public function getPreviewUrlFront(StoreInterface $store)
    {
        $url = $this->urlBuilder->getUrl(
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
        return \NostoHttpRequest::replaceQueryParamsInUrl($params, $url);
    }

    /**
     * Adds the `nostodebug` parameter to a url.
     *
     * @param string $url the url.
     * @return string the updated url.
     */
    public function addNostoDebugParamToUrl($url)
    {
        return \NostoHttpRequest::replaceQueryParamInUrl(
            'nostodebug',
            'true',
            $url
        );
    }
}
