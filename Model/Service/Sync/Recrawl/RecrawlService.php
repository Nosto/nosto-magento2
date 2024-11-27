<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
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
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Service\Sync\Recrawl;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Model\Product\SkuCollection;
use Nosto\Model\Product\VariationCollection;
use Nosto\NostoException;
use Nosto\Operation\RecrawlProduct;
use Nosto\Tagging\Model\Product\Repository as ProductRepository;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Cache\CacheService;
use Nosto\Tagging\Model\Service\Product\DefaultProductService;
use Nosto\Tagging\Util\PagingIterator;

class RecrawlService extends AbstractService
{

    public const BENCHMARK_RECRAWL_NAME = 'nosto_product_recrawl';
    public const BENCHMARK_RECRAWL_BREAKPOINT = 1;

    /** @var CacheService */
    private CacheService $cacheService;

    /** @var DefaultProductService */
    private DefaultProductService $productService;

    /** @var NostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var NostoHelperData */
    protected NostoHelperData $nostoHelperData;

    /** @var NostoHelperUrl */
    private NostoHelperUrl $nostoHelperUrl;

    /** @var ProductRepository */
    private ProductRepository $productRepository;

    /**
     * RecrawlService constructor.
     * @param CacheService $cacheService
     * @param DefaultProductService $productService
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperUrl $nostoHelperUrl
     * @param ProductRepository $productRepository
     * @param NostoLogger $logger
     */
    public function __construct(
        CacheService $cacheService,
        DefaultProductService $productService,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperData $nostoHelperData,
        NostoHelperUrl $nostoHelperUrl,
        ProductRepository $productRepository,
        NostoLogger $logger
    ) {
        $this->cacheService = $cacheService;
        $this->productService = $productService;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->productRepository = $productRepository;
        parent::__construct($nostoHelperData, $nostoHelperAccount, $logger);
    }

    /**
     * Sends products ID's to Nosto to use the crawler to fetch product data
     * @param ProductCollection $collection
     * @param Store $store
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     * @throws AbstractHttpException
     * @throws Exception
     */
    public function recrawl(ProductCollection $collection, Store $store)
    {
        if (!$this->nostoDataHelper->isProductUpdatesEnabled($store)) {
            $this->logDebugWithStore(
                'Nosto product sync is disabled - skipping products recrawl request to Nosto',
                $store
            );
            return;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        $this->startBenchmark(self::BENCHMARK_RECRAWL_NAME, self::BENCHMARK_RECRAWL_BREAKPOINT);

        $timeBetweenBatchOfRequests = $this->getDataHelper()->getRequestTimeout();
        $productsPerRequest = $this->getDataHelper()->getProductsPerRequest();

        $index = 0;
        $collection->setPageSize($productsPerRequest);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $productIdsInBatch = [];
            $this->checkMemoryConsumption('product recrawl request');
            $op = new RecrawlProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout(60);
            $products = $this->productRepository->getByIds(
                $page->getAllIds(
                    $productsPerRequest,
                    ($iterator->getCurrentPageNumber() - 1) * $productsPerRequest
                )
            );
            /** @var Product $product */
            foreach ($products->getItems() as $product) {
                $productIdsInBatch[] = $product->getId();
                $nostoLightProduct = $this->productService->getLightProduct($product, $store);
                if ($nostoLightProduct === null) {
                    throw new NostoException('Could not get product from the product service.');
                }
                $nostoLightProduct->setSkus(new SkuCollection());
                $nostoLightProduct->setVariations(new VariationCollection());

                $op->addProduct($nostoLightProduct);
                // phpcs:ignore
                $this->cacheService->save($nostoLightProduct, $store);
                $this->tickBenchmark(self::BENCHMARK_RECRAWL_NAME);
            }

            $this->logDebugWithStore(
                sprintf(
                    'Upserting batch of %d (%s) - API timeout is set to %d seconds',
                    $productsPerRequest,
                    implode(',', $productIdsInBatch),
                    60
                ),
                $store
            );
            $op->requestRecrawl();
            sleep($timeBetweenBatchOfRequests);
        }
        $this->logBenchmarkSummary(self::BENCHMARK_RECRAWL_NAME, $store, $this);
    }
}
