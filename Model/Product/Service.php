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
use Magento\Store\Model\StoreManager;
use Nosto\Object\Signup\Account;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Api\Data\ProductQueueInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Psr\Log\LoggerInterface;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;

/**
 * Service class for updating products to Nosto
 *
 * @package Nosto\Tagging\Model\Product
 */
class Service
{

    public static $batchSize = 50;
    public static $responseTimeOut = 500;

    private $nostoProductBuilder;
    private $logger;
    private $nostoHelperScope;
    private $nostoHelperAccount;
    private $nostoHelperData;
    private $configurableProduct;
    private $nostoProductRepository;
    private $nostoQueueFactory;
    private $storeManager;

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
     * @param StoreManager $storeManager
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
        QueueFactory $nostoQueueFactory,
        StoreManager $storeManager
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
        $this->storeManager = $storeManager;

        HttpRequest::$responseTimeout = self::$responseTimeOut;
        HttpRequest::buildUserAgent(
            NostoHelperData::PLATFORM_NAME,
            $nostoHelperData->getPlatformVersion(),
            $nostoHelperData->getModuleVersion()
        );
    }

    /**
     * Updates products to Nosto via API
     *
     * @param array $ids array of product ids
     */
    public function updateByIds(array $ids)
    {
        $this->addToQueueByIds($ids);
        $this->flushQueue();
    }

    /**
     * Adds products to queue by id
     *
     * @param Product[] array of product objects
     */
    public function update(array $products)
    {
        $this->addToQueue($products);
        $this->flushQueue();
    }

    /**
     * Adds products to queue by id
     *
     * @param array $ids
     */
    public function addToQueueByIds(array $ids)
    {
        $products = $this->nostoProductRepository->getByIds($ids);
        if ($products->getTotalCount() > 0) {
            $this->addToQueue($products->getItems());
        } else { // If the product id(s) were deleted repo doesn't return any products
            foreach ($ids as $productId) {
                $queue = $this->nostoQueueFactory->create();
                $queue->setProductId($productId);
                $queue->setCreatedAt(new \DateTime('now'));
                $this->nostoQueueRepository->save($queue);
            }
        }
    }

    /**
     * Adds products to queue
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
            $productsForQueue = [];
            $parentProducts = $this->nostoProductRepository->resolveParentProducts($product);
            if (!empty($parentProducts)) {
                foreach ($parentProducts as $parentProduct) {
                    $productsForQueue[] = $parentProduct;
                }
            } else {
                $productsForQueue[] = $product;
            }

            foreach ($productsForQueue as $productForQueue) {
                $queue = $this->nostoQueueFactory->create();
                $queue->setProductId($productForQueue->getId());
                $queue->setCreatedAt(new \DateTime('now'));
                $this->nostoQueueRepository->save($queue);
            }
        }
        $this->logger->info(
            sprintf(
                'Added %d products to Nosto queue',
                $productCount
            )
        );
    }

    /**
     * Updates all products in queue to Nosto
     *
     * @return void
     */
    public function flushQueue()
    {
        $queueEntries = $this->nostoQueueRepository->getAll();
        $queueCount = $queueEntries->getTotalCount();

        $this->logger->info(
            sprintf(
                'Flushing %d products from Nosto queue',
                $queueCount
            )
        );
        $productIds = [];
        foreach ($queueEntries->getItems() as $queueEntry) {
            $productIds[] = $queueEntry->getProductId();
        }
        $this->process($productIds);
    }

    /**
     * Updates products to Nosto by given product ids and store
     *
     * @param array $productIds
     */
    protected function process(array $productIds)
    {
        $uniqueProductIds = array_unique($productIds);
        $leftProducts = $uniqueProductIds;
        $storesWithNosto = $this->getStoresWithNosto();
        $originalStore = $this->storeManager->getStore();
        foreach ($storesWithNosto as $store) {
            $batchCounter = 1;
            $nostoAccount = $this->nostoHelperAccount->findAccount($store);
            $this->storeManager->setCurrentStore($store);
            $productSearch = $this->nostoProductRepository->getByIds($uniqueProductIds);
            if ($productSearch->getTotalCount() == 0) {
                continue;
            }
            $totalBatchCount = ceil($productSearch->getTotalCount()/self::$batchSize);
            $this->logger->info(
                sprintf(
                    'Updating total of %d unique products in %d batches for store %s',
                    $productSearch->getTotalCount(),
                    $totalBatchCount,
                    $store->getName()
                )
            );
            $products = $productSearch->getItems();
            $currentBatchCount = 0;
            $op = new UpsertProduct($nostoAccount);
            /* @var Product $product*/
            foreach ($products as $product) {
                ++$currentBatchCount;
                $key = array_search($product->getId(), $leftProducts);
                if ($key >= 0) {
                    unset($leftProducts[$key]);
                }
                $nostoProduct = $this->nostoProductBuilder->build(
                    $product,
                    $store
                );
                $deleteQueue[] = $product->getId();
                $op->addProduct($nostoProduct);
                if (($currentBatchCount > 0
                    && $currentBatchCount % self::$batchSize == 0)
                    ||  $currentBatchCount == $productSearch->getTotalCount()
                ) {
                    try {
                        $op->upsert();
                        $this->logger->info(
                            sprintf(
                                'Sent %d products (batch %d / %d) to for store %s (%d)',
                                $currentBatchCount,
                                $batchCounter,
                                $totalBatchCount,
                                $store->getName(),
                                $store->getId()
                            )
                        );
                        $this->nostoQueueRepository->deleteByProductIds($deleteQueue);
                    } catch (\Exception $e) {
                        $this->logger->info(
                            sprintf(
                                'Failed to send %d products (batch %d / %d) for store %s (%d)' .
                                ' Error was %s' . ,
                                $currentBatchCount,
                                $batchCounter,
                                $totalBatchCount,
                                $store->getName(),
                                $store->getId(),
                                $e->getMessage()
                            )
                        );
                        $this->logger->error($e->getMessage());
                    }
                    $currentBatchCount = 0;
                    ++$batchCounter;
                }
            }
            $this->storeManager->setCurrentStore($originalStore);
        }

        if (count($leftProducts) > 0) {
            $this->processDelete($leftProducts);
        }
    }

    /**
     * Sends API calls to Nosto to delete / discontinue products
     *
     * @param array $productIds
     */
    protected function processDelete(array $productIds)
    {
        $uniqueProductIds = array_unique($productIds);
        $storesWithNosto = $this->getStoresWithNosto();
        $totalCount = count($uniqueProductIds);
        foreach ($storesWithNosto as $store) {
            $batchCounter = 1;
            $nostoAccount = $this->nostoHelperAccount->findAccount($store);
            if ($nostoAccount instanceof Account === false) {
                continue;
            }
            $totalBatchCount = ceil($totalCount/self::$batchSize);
            $this->logger->info(
                sprintf(
                    'Updating total of %d unique products in %d batches for store %s',
                    $totalCount,
                    $totalBatchCount,
                    $store->getName()
                )
            );
            $currentBatchCount = 0;
            // ToDo - Add DeleteProduct in PHP SDK & call delete
            foreach ($uniqueProductIds as $productId) {
                ++$currentBatchCount;
                // $nostoProduct = $this->nostoProductBuilder->buildForDeletion($productId);
                $deleteQueue[] = $productId;
                if (($currentBatchCount > 0
                        && $currentBatchCount % self::$batchSize == 0)
                    ||  $currentBatchCount == $totalCount
                ) {
                    $this->logger->info(
                        sprintf(
                            'Omitting API call for product delete for %d' .
                            ' products (batch %d / %d) to for store %s (%d)',
                            $currentBatchCount,
                            $batchCounter,
                            $totalCount,
                            $store->getName(),
                            $store->getId()
                        )
                    );
                    $this->nostoQueueRepository->deleteByProductIds($deleteQueue);
                    $currentBatchCount = 0;
                    ++$batchCounter;
                }
            }
        }
    }

    /**
     * Returns an array of stores where Nosto is installed
     *
     * @return array
     */
    protected function getStoresWithNosto()
    {
        $stores = $this->nostoHelperScope->getStores();
        $storesWithNosto = [];
        foreach ($stores as $store) {
            $nostoAccount = $this->nostoHelperAccount->findAccount($store);
            if ($nostoAccount instanceof Account) {
                $storesWithNosto[] = $store;
            }
        }

        return $storesWithNosto;
    }
}
