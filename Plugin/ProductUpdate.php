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
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as MagentoResourceProduct;
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\Store;
use Nosto\Tagging\Exception\ParentProductDisabledException;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\Product\VisibilityResolver;
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

    /** @var VisibilityResolver */
    private VisibilityResolver $visibilityResolver;

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
     * @param VisibilityResolver $visibilityResolver
     */
    public function __construct(
        IndexerRegistry                $indexerRegistry,
        ProductIndexer                 $productIndexer,
        NostoProductRepository         $nostoProductRepository,
        NostoLogger                    $logger,
        ProductUpdateService           $productUpdateService,
        NostoHelperScope               $nostoHelperScope,
        CollectionBuilder              $productCollectionBuilder,
        ProductStoreLink             $productStoreLink,
        VisibilityResolver $visibilityResolver
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->productIndexer = $productIndexer;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->logger = $logger;
        $this->productUpdateService = $productUpdateService;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->productStoreLink = $productStoreLink;
        $this->visibilityResolver = $visibilityResolver;
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

        // A product that becomes "Not Visible Individually" disappears from the
        // individually-visible sync path (see ProductUpdateService::isIndividuallyVisible),
        // so without an explicit discontinue signal it stays an orphan in Nosto. NS-14429.
        if ($this->hasBecomeNotIndividuallyVisible($product)) {
            $storeId = (int)$product->getStoreId();
            $productResource->addCommitCallback(
                function () use ($product, $storeId) {
                    $this->enqueueProductDelete($product, $storeId);
                }
            );
        }

        return $proceed($product);
    }

    /**
     * @param AbstractModel $product
     * @return bool
     */
    private function hasBecomeNotIndividuallyVisible(AbstractModel $product): bool
    {
        if (!$product->getId()) {
            return false;
        }

        $newVisibility = (int)$product->getData(ProductInterface::VISIBILITY);
        if ($newVisibility !== Visibility::VISIBILITY_NOT_VISIBLE) {
            return false;
        }

        // The product is hidden after this save, so the discontinue is warranted on
        // its own. Comparing against the original value only avoids re-sending for a
        // product that was already hidden, and orig data is absent on a partially
        // loaded model - so treat an unknown original as "was visible" and let the
        // (idempotent) discontinue through rather than dropping a real transition.
        $origVisibility = $product->getOrigData(ProductInterface::VISIBILITY);
        return $origVisibility === null
            || (int)$origVisibility !== Visibility::VISIBILITY_NOT_VISIBLE;
    }

    /**
     * Queues a discontinue message for the store(s) affected by this visibility
     * change: just the edited store if it was store-scoped, or every assigned store
     * that has no override of its own if the Default Value was edited
     *
     * @param AbstractModel $product
     * @param int $storeId
     * @return void
     */
    private function enqueueProductDelete(AbstractModel $product, int $storeId): void
    {
        // Store 0 is the admin scope holding the Default Value: it is not a storefront
        // and has no Nosto account of its own, so there is nothing to send it to. An
        // edit made there is instead resolved into the real store views below.
        if ($storeId !== Store::DEFAULT_STORE_ID) {
            try {
                $store = $this->nostoHelperScope->getStore($storeId);
                $this->productUpdateService->addIdsToDeleteMessageQueue([$product->getId()], $store);
                $this->logger->debug(sprintf(
                    'Queued discontinue for product %s on store %s'
                    . ' (visibility changed to Not Visible Individually)',
                    $product->getId(),
                    $store->getCode()
                ));
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
            return;
        }

        try {
            $websiteIds = array_map(
                'intval',
                $this->productStoreLink->getWebsiteIdsByProductId((int)$product->getId())
            );
        } catch (Exception $e) {
            $this->logger->exception($e);
            return;
        }

        // Default Value only changes the fallback used by stores with no override
        // of their own, so each assigned store must be re-checked individually.
        // The check reads post-change visibility, so a store already hidden by its
        // own override before this edit is discontinued again rather than skipped.
        // Deliberate: a repeat discontinue is a no-op for a product Nosto has
        // already dropped, and separating the two cases would cost a second
        // per-store query on every product save.
        foreach ($websiteIds as $websiteId) {
            try {
                $stores = $this->nostoHelperScope->getWebsite($websiteId)->getStores();
                foreach ($stores as $store) {
                    $stillVisible = $this->visibilityResolver->getIndividuallyVisibleProductIds(
                        [(int)$product->getId()],
                        $store
                    );
                    if (empty($stillVisible)) {
                        $this->productUpdateService->addIdsToDeleteMessageQueue([$product->getId()], $store);
                        $this->logger->debug(sprintf(
                            'Queued discontinue for product %s on store %s'
                            . ' (Default Value visibility change, no store override)',
                            $product->getId(),
                            $store->getCode()
                        ));
                    }
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
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
