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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Object\Signup\Account as NostoSignupAccount;
use Nosto\Operation\DeleteProduct;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\Index\Builder;
use Nosto\Tagging\Model\Product\Index\Index as NostoProductIndex;
use Nosto\Tagging\Model\Product\Index\IndexRepository;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as NostoIndexCollectionFactory;
use Nosto\Tagging\Util\Iterator;
use Nosto\Util\Memory as NostoMemUtil;

class Sync
{
    private const API_BATCH_SIZE = 50;
    private const PRODUCT_DELETION_BATCH_SIZE = 100;

    /** @var IndexRepository */
    private $indexRepository;

    /** @var Builder */
    private $indexBuilder;

    /** @var ProductRepository */
    private $productRepository;

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var NostoIndexCollectionFactory */
    private $nostoIndexCollectionFactory;

    /** @var NostoLogger */
    private $logger;

    /** @var TimezoneInterface */
    private $magentoTimeZone;

    /** @var NostoDataHelper */
    private $nostoDataHelper;

    /**
     * Index constructor.
     * @param IndexRepository $indexRepository
     * @param Builder $indexBuilder
     * @param ProductRepository $productRepository
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param NostoIndexCollectionFactory $nostoIndexCollectionFactory
     * @param TimezoneInterface $magentoTimeZone
     * @param NostoDataHelper $nostoDataHelper
     */
    public function __construct(
        IndexRepository $indexRepository,
        Builder $indexBuilder,
        ProductRepository $productRepository,
        NostoProductBuilder $nostoProductBuilder,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        NostoIndexCollectionFactory $nostoIndexCollectionFactory,
        TimezoneInterface $magentoTimeZone,
        NostoDataHelper $nostoDataHelper
    ) {
        $this->indexRepository = $indexRepository;
        $this->indexBuilder = $indexBuilder;
        $this->productRepository = $productRepository;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->logger = $logger;
        $this->nostoIndexCollectionFactory = $nostoIndexCollectionFactory;
        $this->magentoTimeZone = $magentoTimeZone;
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
            $this->logger->info(
                'Nosto product sync is disabled - skipping upserting products to Nosto'
            );
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }

        $collection->setPageSize(self::API_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->eachBatch(function ($collection) use ($account, $store) {
            $this->checkMemoryConsumption('product sync');
            $op = new UpsertProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
            $op->setResponseTimeout(60);
            foreach ($collection as $productIndex) {
                if (!$productIndex->getInSync()) {
                    $op->addProduct($productIndex->getNostoProduct());
                }
            }
            try {
                $op->upsert();
            } catch (\Exception $upsertException) {
                $this->logger->exception($upsertException);
            } finally {
                // We will set this as in sync even if there was failures
                $collection->markAsInSyncCurrentItemsByStore($store);
            }
        });
        try {
            $totalDeleted = $this->purgeDeletedProducts($store);
            $this->logger->info(
                sprintf(
                    'Removed total of %d products from index for store %s',
                    $totalDeleted,
                    $store->getCode()
                )
            );
        } catch (MemoryOutOfBoundsException $e) {
            throw $e;
        } catch (NostoException $e) {
            $this->logger->exception($e);
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
        $totalDeleted = 0;
        $collection->setPageSize(self::PRODUCT_DELETION_BATCH_SIZE);
        $iterator = new Iterator($collection);
        $iterator->eachBatch(function ($collection) use ($account, &$totalDeleted) {
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
                $this->logger->info(
                    sprintf(
                        'Synchronized %d deleted products for store %s to Nosto',
                        $rowsRemoved,
                        $store->getName()
                    )
                );
            } catch (\Exception $e) {
                $this->logger->exception($e);
            }
        });

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

        $this->logger->info(
            sprintf(
                'Removed total of %d products from index for store %s',
                $totalDeleted,
                $store->getCode()
            )
        );
        return $totalDeleted;
    }

    /**
     * Throws new memory out of bounds exception if the memory
     * consumption is higher than configured amount
     *
     * @param string $serviceName
     * @throws MemoryOutOfBoundsException
     */
    private function checkMemoryConsumption($serviceName)
    {
        $maxMemPercentage = $this->nostoDataHelper->getIndexerMemory();
        if (NostoMemUtil::getPercentageUsedMem() >= $maxMemPercentage) {
            throw new MemoryOutOfBoundsException(
                sprintf(
                    'Memory Out Of Bounds Error: Memory used by %s is over %d%% allowed',
                    $serviceName,
                    $maxMemPercentage
                )
            );
        }
    }
}
