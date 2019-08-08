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

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\Index\Builder;
use Nosto\Tagging\Model\Product\Index\Index as NostoProductIndex;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Util\Product as ProductUtil;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class Index
{
    private const API_BATCH_SIZE = 50;
    private const PRODUCT_DATA_BATCH_SIZE = 10;

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
    public function __construct(
        IndexRepository $indexRepository,
        Builder $indexBuilder,
        ProductRepository $productRepository,
        NostoProductBuilder $nostoProductBuilder,
        NostoProductRepository $nostoProductRepository,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger
    ) {
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
     * @param ProductCollection $collection
     * @param Store $store
     */
    public function handleProductChange(ProductCollection $collection, Store $store)
    {
        foreach ($collection as $product) {
            $this->updateOrCreateDirtyEntity($product, $store);
        }
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return ProductIndexInterface|null
     */
    public function updateOrCreateDirtyEntity(Product $product, Store $store)
    {
        $indexedProduct = $this->indexRepository->getByProductIdAndStoreId($product->getId(), $store->getId());
        try {
            if ($indexedProduct instanceof ProductIndexInterface) {
                $indexedProduct->setIsDirty(true);
                $indexedProduct->setUpdatedAt(new \DateTime('now'));
            } else {
                $fullProduct = $this->productRepository->getById($product->getId()); // We need to load the full product
                $indexedProduct = $this->indexBuilder->build($fullProduct, $store);
                $indexedProduct->setIsDirty(false);
            }
            $this->indexRepository->save($indexedProduct);
            return $indexedProduct;
        } catch (\Exception $e) {
            $this->logger->exception($e);
            return null;
        }
    }

    /**
     * @param NostoIndexCollection $collection
     * @throws NoSuchEntityException
     */
    public function handleDirtyProducts(NostoIndexCollection $collection)
    {
        $totalItems = $collection->getSize();
        $collection->setPageSize(self::PRODUCT_DATA_BATCH_SIZE);
        $lastPage = $collection->getLastPageNumber();
        $this->logger->logWithMemoryConsumption(
            sprintf(
                'Rebuilding total of %d dirty products in %d batches',
                $totalItems,
                $lastPage
            )
        );
        $curPage = 1;
        do {
            $collection->clear();
            $collection->setCurPage($curPage);
            $collection->load();
            foreach ($collection as $productIndex) {
                if ($productIndex->getIsDirty() === NostoProductIndex::VALUE_IS_DIRTY) {
                    $this->rebuildDirtyProduct($productIndex);
                }
            }
            ++$curPage;
        } while ($curPage <= $lastPage);
    }

    /**
     * Handles sync of product collection by sending it to Nosto through API
     *
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws NostoException
     */
    public function handleProductSync(NostoIndexCollection $collection, Store $store): void
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        try {
            $collection->setPageSize(self::API_BATCH_SIZE);
            $pages = $collection->getLastPageNumber();
            $currentPage = 1;
            $totalCount = $collection->getSize();
            $sentProducts = 0;
            $this->logger->logWithMemoryConsumption(
                sprintf(
                    'Synchronizing total of %d product to Nosto',
                    $totalCount
                )
            );
            do {
                $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
                $op->setResponseTimeout(60);
                $collection->clear();
                $collection->setCurPage($currentPage);
                /* @var NostoProductIndex $productIndex */
                foreach ($collection as $productIndex) {
                    $op->addProduct($productIndex->getNostoProduct());
                    $productIndex->setInSync(true);
                    $this->indexRepository->save($productIndex);
                }
                try {
                    $op->upsert();
                    $sentProducts += $collection->count();
                    $this->logger->logWithMemoryConsumption(
                        sprintf(
                        'Sent %d/%d products to Nosto (%d/%d)',
                            $sentProducts,
                            $totalCount,
                            $currentPage,
                            $pages
                        )
                    );
                } catch (\Exception $upsertException) {
                    $this->logger->exception($upsertException);
                }
                ++$currentPage;
            } while ($currentPage <= $pages);
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }

    /**
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface|null
     * @throws NoSuchEntityException
     */
    public function rebuildDirtyProduct(ProductIndexInterface $productIndex)
    {
        // ToDo - if the product doesn't exist this throws an error -> perhaps we can use that for detecting deletions
        $magentoProduct = $this->productRepository->getById($productIndex->getProductId());
        $store = $this->nostoHelperScope->getStore($productIndex->getStoreId());
        try {
            $nostoProduct = $this->nostoProductBuilder->build($magentoProduct, $store);
            $nostoIndexedProduct = $productIndex->getNostoProduct();
            if ($nostoIndexedProduct instanceof NostoProductInterface === false ||
                (
                    $nostoProduct instanceof NostoProductInterface
                    && !ProductUtil::isEqual($nostoProduct, $nostoIndexedProduct)
                )
            ) {
                $productIndex->setNostoProduct($nostoProduct);
                $productIndex->setInSync(false);
            }
            $productIndex->setIsDirty(false);
            $this->indexRepository->save($productIndex);
            return $productIndex;
        } catch (\Exception $e) {
            $this->logger->exception($e);
            return null;
        }
    }

    /**
     * Defines product index as in sync
     *
     * @param ProductIndexInterface $productIndex
     * @throws \Exception
     * @return void
     */
    public function markAsInSync(ProductIndexInterface $productIndex)
    {
        if (!$productIndex->getInSync()) {
            $productIndex->setInSync(true);
            $this->indexRepository->save($productIndex);
        }
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @return void
     * @throws \Exception
     */
    public function markAsInSyncProductByIdAndStore($productId, $storeId)
    {
        try {
            $productIndex = $this->indexRepository->getByProductIdAndStoreId($productId, $storeId);
            if ($productIndex instanceof ProductIndexInterface) {
                $this->markAsInSync($productIndex);
            }
        } catch (\Exception $e) {
            $this->logger->exception($e);
        }
    }
}
