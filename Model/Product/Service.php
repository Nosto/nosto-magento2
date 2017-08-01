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

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\QueueRepository;
use Nosto\Tagging\Model\Product\QueueFactory;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;

/**
 * Service class for updating products to Nosto
 *
 * @package Nosto\Tagging\Model\Product
 */
class Service
{

    public static $batchSize = 10;

    private $nostoProductBuilder;
    private $logger;
    private $nostoHelperScope;
    private $nostoHelperAccount;
    private $nostoHelperData;
    private $configurableProduct;
    private $nostoProductRepository;
    private $nostoQueueRepositoryFactory;
    private $nostoQueueFactory;

    public $processed = [];

    /**
     * Constructor to instantiating the product update command. This constructor uses proxy classes for
     * two of the Nosto objects to prevent introspection of constructor parameters when the DI
     * compile command is run.
     * Not using the proxy classes will lead to a "Area code not set" exception being thrown in the
     * compile phase.
     * @param LoggerInterface $logger
     * @param NostoHelperScope\Proxy $nostoHelperScope
     * @param Builder $nostoProductBuilder
     * @param ConfigurableProduct $configurableProduct
     * @param NostoHelperAccount\Proxy $nostoHelperAccount
     * @param NostoHelperData\Proxy $nostoHelperData
     * @param NostoProductRepository\Proxy $nostoProductRepository
     * @param QueueRepository $nostoQueueRepository
     * @param QueueFactory $nostoQueueFactory
     */
    public function __construct(
        LoggerInterface $logger,
        NostoHelperScope\Proxy $nostoHelperScope,
        NostoProductBuilder $nostoProductBuilder,
        ConfigurableProduct $configurableProduct,
        NostoHelperAccount\Proxy $nostoHelperAccount,
        NostoHelperData\Proxy $nostoHelperData,
        NostoProductRepository\Proxy $nostoProductRepository,
        QueueRepository $nostoQueueRepository,
        QueueFactory $nostoQueueFactory
    )
    {
        $this->logger = $logger;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->configurableProduct = $configurableProduct;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->nostoQueueRepository = $nostoQueueRepository;
        $this->nostoQueueFactory = $nostoQueueFactory;

        HttpRequest::$responseTimeout = 120;
        HttpRequest::buildUserAgent(
            NostoHelperData::PLATFORM_NAME,
            $nostoHelperData->getPlatformVersion(),
            $nostoHelperData->getModuleVersion()
        );
    }

    /**
     * Updates product collection to Nosto via API
     *
     * @param Product[] $products
     */
    public function addToQueue(array $products)
    {
        $productCount = count($products);

        $this->logger->info(
            sprintf(
                'Adding %d products to Nosto queue',
                $productCount
            )
        );
        foreach ($products as $product) {
            $queue = $this->nostoQueueFactory->create();
            $queue->setProductId($product->getId());
            $queue->setCreatedAt(new \DateTime('now'));
            $this->nostoQueueRepository->save($queue);
        }
    }

    /**
     * Updates product collection to Nosto via API
     *
     * @param Product[] $products
     */
    public function update(array $products)
    {
        $productsInStores = [];
        $storesWithNoNosto = [];
        $currentBatch = 0;
        $productCounter = 0;
        $productCount = count($products);
        $this->logger->info(
            sprintf(
                'Starting to sync %d products to Nosto with batch size %d',
                $productCount,
                self::$batchSize
            )
        );

        foreach ($products as $product) {
            if ($this->isProcessed($product)) {
                continue;
            }
            foreach ($product->getStoreIds() as $storeId) {
                if (in_array($storeId, $storesWithNoNosto)) {
                    continue;
                }
                if (empty($productsInStores[$storeId])) {
                    $store = $this->nostoHelperScope->getStore($storeId);
                    $account = $this->nostoHelperAccount->findAccount($store);
                    if ($account === null) {
                        $storesWithNoNosto[] = $storeId;
                        continue;
                    }
                    $productsInStores[$storeId] = [];
                }
                $parentProducts = $this->nostoProductRepository->resolveParentProducts($product);
                $productsToUpdate = [];
                if ($parentProducts instanceof ProductCollection) {
                    foreach ($parentProducts as $parentProduct) {
                        $productsToUpdate[] = $parentProduct;
                    }
                } else {
                    $productsToUpdate[] = $product;
                }
                foreach ($productsToUpdate as $productToUpdate) {
                    if ($productCounter > 0
                        && $productCounter % self::$batchSize == 0
                    ) {
                        ++$currentBatch;
                    }
                    if (empty($productsInStores[$storeId][$currentBatch])) {
                        $productsInStores[$storeId][$currentBatch] = [];
                    }
                    $productsInStores[$storeId][$currentBatch][] = $productToUpdate;
                }
            }
            ++$productCounter;
            $this->setProcessed($product);
        }
        $totalBatches = $currentBatch+1;
        $this->logger->info(
            sprintf(
                'Product batches (%d) ready for %d store(s)',
                $totalBatches,
                count($productsInStores)
            )
        );

        foreach ($productsInStores as $storeId => $batches) {
            $store = $this->nostoHelperScope->getStore($storeId);
            $account = $this->nostoHelperAccount->findAccount($store);
            if ($account === null) {
                continue;
            }
            if (!$this->nostoHelperData->isProductUpdatesEnabled($store)) {
                continue;
            }
            foreach ($batches as $batch => $products) {
                $op = new UpsertProduct($account);
                $productsAdded = 0;
                foreach ($products as $product) {
                    $nostoProduct = $this->nostoProductBuilder->build(
                        $product,
                        $store
                    );
                    if ($nostoProduct === null) {
                        continue;
                    }
                    ++$productsAdded;
                    $op->addProduct($nostoProduct);
                }
                try {
                    if ($productsAdded > 0) {
                        $op->upsert($op);
                        $this->logger->info(
                            sprintf(
                                'Sent batch %d to for store %s (%d)',
                                $batch,
                                $store->getName(),
                                $store->getId()
                            )
                        );
                    }
                } catch (NostoException $e) {
                    $this->logger->info(
                        sprintf(
                            'Failed to send batch %d / %d for store %s (%d)',
                            $batch,
                            $totalBatches,
                            $store->getName(),
                            $store->getId()
                        )
                    );
                    $this->logger->error($e->getMessage());
                }
            }
        }
        $this->logger->info('Product sync finished');
    }

    /**
     * Sets a product id as processed / queued
     *
     * @param Product $product
     */
    private function setProcessed(Product $product)
    {
        // Get parent product and all skus
        $parentProduct = $this->nostoProductRepository->resolveParentProducts($product);
        
    }

    /**
     * Checks if product has been already processed
     *
     * @param Product $product
     * @return bool
     */
    private function isProcessed(Product $product)
    {
        $found = false;
        if (isset($this->processed[$product->getId()])) {
            $found = true;
        } else {
            foreach ($this->processed as $parentId => $arr) {
                if (in_array($product->getId(), $arr)) {
                    $found = true;
                    break;
                }
            }
        }

        return $found;
    }
}
