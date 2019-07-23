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

namespace Nosto\Tagging\Model\Service;

use Magento\Store\Model\Store;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Model\Product\Index\Builder;
use Magento\Catalog\Model\ProductRepository;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Util\Product as ProductUtil;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;

class Index
{
    const IS_DIRTY = "1";
    const NOT_IN_SYNC = "0";

    /** @var IndexRepository */
    private $indexRepository;

    /** @var Builder */
    private $indexBuilder;

    /** @var ProductRepository */
    private $productRepository;

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /** @var NostoProductRepository */
    private $nostoProductRepository;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var NostoLogger */
    private $logger;

    /**
     * Index constructor.
     * @param IndexRepository $indexRepository
     * @param Builder $indexBuilder
     * @param ProductRepository $productRepository
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoProductRepository $nostoProductRepository
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     */
    public function __construct(IndexRepository $indexRepository, Builder $indexBuilder, ProductRepository $productRepository, NostoProductBuilder $nostoProductBuilder, NostoProductRepository $nostoProductRepository, NostoHelperScope $nostoHelperScope, NostoHelperAccount $nostoHelperAccount, NostoHelperUrl $nostoHelperUrl, NostoLogger $logger)
    {
        $this->indexRepository = $indexRepository;
        $this->indexBuilder = $indexBuilder;
        $this->productRepository = $productRepository;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->logger = $logger;
    }

    /**
     * Handles only the first step of indexing
     * Create one if row does not exits
     * Else set row to dirty
     *
     * @param int $productId
     * @param Store $store
     */
    public function handleProductChange(int $productId, Store $store)
    {
        try {
            $finalProductIds = $this->getFinalProductsIds($productId);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }

        foreach ($finalProductIds as $finalProductId) {
            $this->updateOrCreateDirtyEntity($finalProductId, $store);
        }
    }

    /**
     * @param int $productId
     * @param Store $store
     */
    private function updateOrCreateDirtyEntity(int $productId, Store $store)
    {
        $indexedProduct = $this->indexRepository->getByProductIdAndStoreId($productId, $store->getId());
        try {
            if ($indexedProduct instanceof ProductIndexInterface) {
                $indexedProduct->setIsDirty(true);
                $indexedProduct->setUpdatedAt(new \DateTime('now'));
            } else {
                $product = $this->productRepository->getById($productId);
                $indexedProduct = $this->indexBuilder->build($product, $store);
            }
            $this->indexRepository->save($indexedProduct);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param int $rowId
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function handleDirtyProduct(int $rowId)
    {
        $productIndex = $this->indexRepository->getById($rowId);
        if ($productIndex instanceof ProductIndexInterface &&
            $productIndex->getIsDirty() === self::IS_DIRTY) {
            $this->rebuildDirtyProduct($productIndex);
        }
    }

    /**
     * Handles sync of product by sending it to Nosto through API
     *
     * @param int $rowId
     */
    public function handleProductSync(int $rowId)
    {
        $productIndex = $this->indexRepository->getById($rowId);
        if ($productIndex instanceof ProductIndexInterface &&
            $productIndex->getInSync() === self::NOT_IN_SYNC) {
            $this->syncProductToNosto($productIndex);
        }
    }

    /**
     * @param ProductIndexInterface $productIndex
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function rebuildDirtyProduct(ProductIndexInterface $productIndex)
    {
        $magentoProduct = $this->productRepository->getById($productIndex->getProductId());
        $store = $this->nostoHelperScope->getStore($productIndex->getStoreId());
        try {
            $nostoProduct = $this->nostoProductBuilder->build($magentoProduct, $store);
            if ($nostoProduct instanceof NostoProductInterface &&
                !ProductUtil::isEqual($nostoProduct, $productIndex->getNostoProduct())) {
                $productIndex->setNostoProduct($nostoProduct);
                $productIndex->setInSync(false);
            }
            $productIndex->setIsDirty(false);
            $this->indexRepository->save($productIndex);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * Get parent product ids
     *
     * @param int $productId
     * @return array|int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getFinalProductsIds($productId)
    {
        $magentoProduct = $this->productRepository->getById($productId);
        $parentIds = $this->nostoProductRepository->resolveParentProductIds($magentoProduct);
        if ($parentIds === null) {
            return $productId;
        }
        return array_unique($parentIds);
    }

    /**
     * @param ProductIndexInterface $productIndex
     */
    private function syncProductToNosto(ProductIndexInterface $productIndex)
    {
        $store = $this->nostoHelperScope->getStore($productIndex->getStoreId());
        $account = $this->nostoHelperAccount->findAccount($store);
        try {
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->addProduct($productIndex->getNostoProduct());
            $op->upsert();
            $productIndex->setInSync(true);
            $this->indexRepository->save($productIndex);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }
}