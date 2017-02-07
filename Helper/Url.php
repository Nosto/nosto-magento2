<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Helper;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Api\Data\StoreInterface;
use NostoHttpRequest;

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
     * Adds the `nostodebug` parameter to a url.
     *
     * @param string $url the url.
     * @return string the updated url.
     */
    public function addNostoDebugParamToUrl($url)
    {
        return NostoHttpRequest::replaceQueryParamInUrl(
            'nostodebug',
            'true',
            $url
        );
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
                ['___store' => $store->getCode()],
                $url
            );
            return $this->addNostoDebugParamToUrl($url);
        }

        return '';
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
        return NostoHttpRequest::replaceQueryParamsInUrl($params, $url);
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
            [
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            ]
        );
        $url = $this->replaceQueryParamsInUrl(['q' => 'nosto'], $url);
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
            [
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            ]
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
            [
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            ]
        );
        return $this->addNostoDebugParamToUrl($url);
    }
}
