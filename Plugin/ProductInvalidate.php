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

namespace Nosto\Tagging\Plugin;

use Closure;
use Magento\Catalog\Model\ResourceModel\Product as MagentoResourceProduct;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Model\Indexer\Invalidate as IndexerInvalidate;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\Service\Cache\CacheService;

class ProductInvalidate
{
    /** @var IndexerRegistry  */
    private $indexerRegistry;

    /** @var IndexerInvalidate  */
    private $indexerInvalidate;

    /** @var CacheService  */
    private $cacheService;

    /** @var NostoProductRepository  */
    private $nostoProductRepository;

    /**
     * ProductInvalidate constructor.
     * @param IndexerRegistry $indexerRegistry
     * @param CacheService $cacheService
     * @param IndexerInvalidate $indexerInvalidate
     */
    public function __construct(
        IndexerRegistry $indexerRegistry,
        CacheService $cacheService,
        IndexerInvalidate $indexerInvalidate,
        NostoProductRepository $nostoProductRepository
    )
    {
        $this->indexerRegistry = $indexerRegistry;
        $this->cacheService = $cacheService;
        $this->indexerInvalidate = $indexerInvalidate;
        $this->nostoProductRepository = $nostoProductRepository;
    }

    /**
     * @param MagentoResourceProduct $productResource
     * @param Closure $proceed
     * @param AbstractModel $product
     * @return mixed
     */
    public function aroundSave(
        MagentoResourceProduct $productResource,
        Closure $proceed,
        AbstractModel $product
    ) {
        $mageIndexer = $this->indexerRegistry->get(IndexerInvalidate::INDEXER_ID);
        if (!$mageIndexer->isScheduled()) {
            $productResource->addCommitCallback(function () use ($product) {
                $this->indexerInvalidate->executeRow($product->getId());
            });
        }

        return $proceed($product);
    }

    /**
     * @param MagentoResourceProduct $productResource
     * @param Closure $proceed
     * @param AbstractModel $product
     * @return mixed
     * @suppress PhanTypeMismatchArgument
     */
    public function aroundDelete(
        MagentoResourceProduct $productResource,
        Closure $proceed,
        AbstractModel $product
    ) {
        $mageIndexer = $this->indexerRegistry->get(IndexerInvalidate::INDEXER_ID);
        if (!$mageIndexer->isScheduled()) {
            $productIds = $this->nostoProductRepository->resolveParentProductIds($product);
            if (empty($productIds) && $this->cacheService->canBuildProduct($product)) {
                $productResource->addCommitCallback(function () use ($product) {
                    $this->indexerInvalidate->executeRow($product->getId());
                });
            }
            if (is_array($productIds) && !empty($productIds)) {
                $productResource->addCommitCallback(function () use ($productIds) {
                    $this->indexerInvalidate->executeList($productIds);
                });
            }
        }

        return $proceed($product);
    }
}
