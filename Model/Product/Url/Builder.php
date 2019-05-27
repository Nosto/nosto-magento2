<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Product\Url;

use Magento\Catalog\Model\Product;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\DataObject;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Nosto\Tagging\Helper\Data as NostoDataHelper;

/**
 * Url builder class cannibalised from the Magento core. When trying to get the URL of a product
 * from the Magento backend, the DI rules inject inject a reference to \Magento\Backend\Model\Url
 * instead of \Magento\Framework\Url. Both these classes implement the Magento\Framework\UrlInterface
 * and are responsible for URL building. The building of the route parameters is the same whether
 * it occurs on the backend or the frontend but the actual building of the URL differs from the
 * backend to the frontend.
 * <br />
 * There's no clean way of changing this behaviour without modifying the core so this class contains
 * code from \Magento\Catalog\Model\Product\Url which now always uses the frontend version of the
 * Magento\Framework\UrlInterface class.
 *
 * @package Nosto\Tagging\Model\Product\Url
 */
class Builder extends DataObject
{
    private $urlFinder;
    private $urlFactory;
    private $nostoDataHelper;

    /**
     * @param Url $urlFactory
     * @param UrlFinderInterface $urlFinder
     * @param NostoDataHelper $nostoDataHelper
     * @param array $data
     */
    public function __construct(
        Url $urlFactory,
        UrlFinderInterface $urlFinder,
        NostoDataHelper $nostoDataHelper,
        array $data = []
    ) {
        parent::__construct($data);
        $this->urlFinder = $urlFinder;
        $this->urlFactory = $urlFactory;
        $this->nostoDataHelper = $nostoDataHelper;
    }

    public function getUrlInStore(Product $product, Store $store)
    {
        $routeParams = [];
        $routePath = '';
        $filterData = [
            UrlRewrite::ENTITY_ID => $product->getId(),
            UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::STORE_ID => $store->getId(),
        ];
        $rewrite = $this->urlFinder->findOneByData($filterData);
        if ($rewrite) {
            $routeParams['_direct'] = $rewrite->getRequestPath();
        } else { // If the rewrite is not found fallback to the "ugly version" of the URL
            $routePath = 'catalog/product/view';
            $routeParams['id'] = $product->getId();
            $routeParams['s'] = $product->getUrlKey();
        }
        $routeParams['_nosid'] = true;          // Remove the session identifier from the URL
        $routeParams['_scope'] = $store->getCode();      // Specify the store identifier for the URL
        $routeParams['_scope_to_url'] = $this->nostoDataHelper->getStoreCodeToUrl($store);
        $routeParams['_query'] = [];            // Reset the cached URL instance GET query params

        return $this->urlFactory->setScope($store->getId())
            ->getUrl($routePath, $routeParams);
    }
}
