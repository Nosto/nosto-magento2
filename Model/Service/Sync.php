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
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Index\Index as NostoProductIndex;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as NostoIndexCollectionFactory;
use Nosto\Tagging\Util\Iterator;

class Sync extends AbstractService
{
    private const API_BATCH_SIZE = 50;
    private const PRODUCT_DELETION_BATCH_SIZE = 100;
    private const BENCHMARK_SYNC_NAME = 'nosto_product_sync';
    private const BENCHMARK_SYNC_BREAKPOINT = 1;
    private const BENCHMARK_DELETE_NAME = 'nosto_product_delete';
    private const BENCHMARK_DELETE_BREAKPOINT = 1;
    private const RESPONSE_TIMEOUT = 60;

    /** @var IndexRepository */
    private $indexRepository;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var NostoIndexCollectionFactory */
    private $nostoIndexCollectionFactory;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /**
     * Index constructor.
     * @param IndexRepository $indexRepository
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param NostoIndexCollectionFactory $nostoIndexCollectionFactory
     * @param NostoDataHelper $nostoDataHelper
     */
    public function __construct(
        IndexRepository $indexRepository,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        NostoIndexCollectionFactory $nostoIndexCollectionFactory,
        NostoDataHelper $nostoDataHelper
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->indexRepository = $indexRepository;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoIndexCollectionFactory = $nostoIndexCollectionFactory;
        $this->nostoDataHelper = $nostoDataHelper;
    }

    /**
     * Handles sync of product collection by sending it to Nosto
     *
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function syncIndexedProducts(NostoIndexCollection $collection, Store $store)
    {
        if (!$this->nostoDataHelper->isProductUpdatesEnabled($store)) {
            $this->getLogger()->info(
                'Nosto product sync is disabled - skipping upserting products to Nosto'
            );
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $this->startBenchmark(self::BENCHMARK_SYNC_NAME, self::BENCHMARK_SYNC_BREAKPOINT);
        $collection->setPageSize(self::API_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->eachBatch(function (NostoIndexCollection $collectionBatch) use ($account, $store) {
            $this->checkMemoryConsumption('product sync');
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout(self::RESPONSE_TIMEOUT);
            foreach ($collectionBatch as $productIndex) {
                if (!$productIndex->getInSync()) {
                    $op->addProduct($productIndex->getNostoProduct());
                }
            }
            try {
                $op->upsert();
                $this->tickBenchmark(self::BENCHMARK_SYNC_NAME);
            } catch (\Exception $upsertException) {
                $this->getLogger()->exception($upsertException);
            } finally {
                // We will set this as in sync even if there was failures
                $collectionBatch->markAsInSyncCurrentItemsByStore($store);
            }
        });
        $this->logBenchmarkSummary(self::BENCHMARK_SYNC_NAME, $store);
        try {
            $totalDeleted = $this->purgeDeletedProducts($store);
            $this->getLogger()->info(
                sprintf(
                    'Removed total of %d products from index for store %s',
                    $totalDeleted,
                    $store->getCode()
                )
            );
        } catch (MemoryOutOfBoundsException $e) {
            throw $e;
        } catch (NostoException $e) {
            $this->getLogger()->exception($e);
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
     * @param int[] $productIds
     * @param Store $store
     * @return void
     */
    public function markAsInSyncByProductIdsAndStoreId(array $productIds, Store $store)
    {
        try {
            $this->nostoIndexCollectionFactory->create()->markAsInSync($productIds, $store);
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * Discontinues products in Nosto and removes indexed products from Nosto product index
     *
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @return int number of deleted products
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function deleteIndexedProducts(NostoIndexCollection $collection, Store $store)
    {
        if ($collection->getSize() === 0) {
            return 0;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $this->startBenchmark(self::BENCHMARK_DELETE_NAME, self::BENCHMARK_DELETE_BREAKPOINT);
        $totalDeleted = 0;
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->eachBatch(function (NostoIndexCollection $collection) use ($account, $store, &$totalDeleted) {
            $this->checkMemoryConsumption('product delete');
            $ids = [];
            /* @var $indexedProduct NostoProductIndex */
            foreach ($collection as $indexedProduct) {
                $ids[] = $indexedProduct->getId();
            }
            try {
                $op = new DeleteProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
                $op->setResponseTimeout(30);
                $op->setProductIds($ids);
                $op->delete(); // @codingStandardsIgnoreLine
                $rowsRemoved = $collection->deleteCurrentItemsByStore($store);
                $totalDeleted += $rowsRemoved;
                $this->tickBenchmark(self::BENCHMARK_DELETE_NAME);
            } catch (\Exception $e) {
                $this->getLogger()->exception($e);
            }
        });
        $this->logBenchmarkSummary(self::BENCHMARK_DELETE_NAME, $store);
        return $totalDeleted;
    }

    /**
     * Fetches deleted products from the product index, sends those to Nosto
     * and deletes the deleted rows from database
     *
     * @param Store $store
     * @return int the amount of deleted products
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function purgeDeletedProducts(Store $store)
    {
        $collection = $this->nostoIndexCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addIsDeletedFilter()
            ->addStoreFilter($store);
        $totalDeleted = $this->deleteIndexedProducts($collection, $store);

        $this->getLogger()->info(
            sprintf(
                'Removed total of %d products from index for store %s',
                $totalDeleted,
                $store->getCode()
            )
        );
        return $totalDeleted;
    }
}
