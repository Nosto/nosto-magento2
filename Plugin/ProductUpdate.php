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

namespace Nosto\Tagging\Plugin;

use Closure;
use Exception;
use Magento\Catalog\Model\ResourceModel\Product as MagentoResourceProduct;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Nosto\Tagging\Exception\ParentProductDisabledException;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;

/**
 * Plugin for product updates
 */
class ProductUpdate
{
    /** @var IndexerRegistry  */
    private IndexerRegistry $indexerRegistry;

    /** @var ProductIndexer  */
    private ProductIndexer $productIndexer;

    /** @var NostoProductRepository  */
    private NostoProductRepository $nostoProductRepository;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /** @var CollectionBuilder */
    private CollectionBuilder $productCollectionBuilder;

    /** @var ProductStoreLink */
    private ProductStoreLink $productStoreLink;

    /**
     * ProductUpdate constructor.
     * @param IndexerRegistry $indexerRegistry
     * @param ProductIndexer $productIndexer
     * @param NostoProductRepository $nostoProductRepository
     * @param NostoLogger $logger
     * @param ProductUpdateService $productUpdateService
     * @param NostoHelperScope $nostoHelperScope
     * @param CollectionBuilder $productCollectionBuilder
     * @param ProductStoreLink $productStoreLink
     */
    public function __construct(
        IndexerRegistry                $indexerRegistry,
        ProductIndexer                 $productIndexer,
        NostoProductRepository         $nostoProductRepository,
        NostoLogger                    $logger,
        ProductUpdateService           $productUpdateService,
        NostoHelperScope               $nostoHelperScope,
        CollectionBuilder              $productCollectionBuilder,
        ProductStoreLink             $productStoreLink
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->productIndexer = $productIndexer;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->logger = $logger;
        $this->productUpdateService = $productUpdateService;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->productStoreLink = $productStoreLink;
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
        $mageIndexer = $this->indexerRegistry->get(ProductIndexer::INDEXER_ID);
        if (!$mageIndexer->isScheduled()) {
            $productResource->addCommitCallback(function () use ($product) {
                $this->productIndexer->executeRow($product->getId());
            });
        }

        // A product removed from a Store disappears from the store-filtered indexer
        // collections, so the update pipeline can never mark it discontinued for the
        // stores it left. Capture the persisted Store assignments before the save
        // and diff them against the database once the transaction has committed.
        $storeIdsBeforeSave = $this->getPersistedStoreIds($product);
        if (!empty($storeIdsBeforeSave)) {
            $productResource->addCommitCallback(
                function () use ($product, $storeIdsBeforeSave) {
                    $this->queueDiscontinueForRemovedStores($product, $storeIdsBeforeSave);
                }
            );
        }

        return $proceed($product);
    }

    /**
     * @param AbstractModel $product
     * @return int[]
     */
    private function getPersistedStoreIds(AbstractModel $product): array
    {
        if (!$product->getId()) {
            return [];
        }
        return array_map('intval', $this->productStoreLink->getWebsiteIdsByProductId((int)$product->getId()));
    }

    /**
     * Queues a discontinue message for every store view belonging to a Store
     * the product was unassigned from during the save
     *
     * @param AbstractModel $product
     * @param int[] $storeIdsBeforeSave
     * @return void
     */
    private function queueDiscontinueForRemovedStores(
        AbstractModel $product,
        array $storeIdsBeforeSave
    ): void {
        try {
            $storeIdsAfterSave = array_map(
                'intval',
                $this->productStoreLink->getWebsiteIdsByProductId((int)$product->getId())
            );
        } catch (Exception $e) {
            $this->logger->exception($e);
            return;
        }

        $removedStoreIds = array_diff($storeIdsBeforeSave, $storeIdsAfterSave);
        foreach ($removedStoreIds as $storeId) {
            try {
                $stores = $this->nostoHelperScope->getWebsite($storeId)->getStores();
                foreach ($stores as $store) {
                    $this->productUpdateService->addIdsToDeleteMessageQueue([$product->getId()], $store);
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
    }

    /**
     * @param MagentoResourceProduct $productResource
     * @param Closure $proceed
     * @param AbstractModel $product
     * @return mixed
     * @suppress PhanTypeMismatchArgument
     * @noinspection PhpParamsInspection
     */
    public function aroundDelete(
        MagentoResourceProduct $productResource,
        Closure $proceed,
        AbstractModel $product
    ) {
        $productId = $product->getId();
        try {
            $productIds = $this->nostoProductRepository->resolveParentProductIds($product);
        } catch (ParentProductDisabledException $e) {
            $this->logger->debug(
                sprintf(
                    'Product ID %s: %s',
                    $productId,
                    $e->getMessage()
                )
            );
            return $proceed($product);
        }

        $storeIds = $product->getStoreIds();
        $this->logger->debug(
            sprintf(
                'Product ID %s deleted from Magento. Store IDs: %s. Parent product IDs: %s',
                $productId,
                !empty($storeIds) ? implode(',', $storeIds) : 'none',
                !empty($productIds) ? implode(',', $productIds) : 'none'
            )
        );

        // The current product does not have parent product
        if (empty($productIds)) {
            $productResource->addCommitCallback(function () use ($product, $productId, $storeIds) {
                foreach ($storeIds as $storeId) {
                    $store = $this->nostoHelperScope->getStore($storeId);
                    $this->logger->debug(
                        sprintf(
                            'Queuing product ID %s for deletion in Nosto. Store ID: %s',
                            $productId,
                            $storeId
                        )
                    );
                    $this->productUpdateService->addIdsToDeleteMessageQueue([$product->getId()], $store);
                }
            });
        }

        // Current product is child product
        if (is_array($productIds) && !empty($productIds)) {
            $productResource->addCommitCallback(function () use ($productIds, $storeIds, $productId) {
                $productCollection = $this->productCollectionBuilder->withIds($productIds)->build();

                foreach ($storeIds as $storeId) {
                    $store = $this->nostoHelperScope->getStore($storeId);
                    $this->logger->debug(
                        sprintf(
                            'Product ID %s deleted. Queuing parent product IDs %s for update in Nosto.'
                            . ' Store ID: %s',
                            $productId,
                            implode(',', $productIds),
                            $storeId
                        )
                    );
                    $this->productUpdateService->addCollectionToUpdateMessageQueue($productCollection, $store);
                }
            });
        }

        return $proceed($product);
    }
}
