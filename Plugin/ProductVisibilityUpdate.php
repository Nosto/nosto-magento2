<?php
/**
 * Copyright (c) 2026, Nosto Solutions Ltd
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
 * @copyright 2026 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Plugin;

use Closure;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\VisibilityResolver;
use Nosto\Tagging\Model\ResourceModel\Product\WebsiteLink;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;

/**
 * Plugin for the product grid's mass "Update Attributes" action.
 *
 * Action::updateAttributes() writes EAV values directly via its own resource model
 * and never touches ResourceModel\Product::save(), so Plugin\ProductUpdate::aroundSave
 * never runs for it. Without this plugin, bulk-hiding many products at once (the
 * most common way to trigger NS-14429) would leave them as orphans in Nosto forever.
 */
class ProductVisibilityUpdate
{
    /** @var VisibilityResolver */
    private VisibilityResolver $visibilityResolver;

    /** @var WebsiteLink */
    private WebsiteLink $productWebsiteLink;

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * @param VisibilityResolver $visibilityResolver
     * @param WebsiteLink $productWebsiteLink
     * @param NostoHelperScope $nostoHelperScope
     * @param ProductUpdateService $productUpdateService
     * @param NostoLogger $logger
     */
    public function __construct(
        VisibilityResolver $visibilityResolver,
        WebsiteLink $productWebsiteLink,
        NostoHelperScope $nostoHelperScope,
        ProductUpdateService $productUpdateService,
        NostoLogger $logger
    ) {
        $this->visibilityResolver = $visibilityResolver;
        $this->productWebsiteLink = $productWebsiteLink;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->productUpdateService = $productUpdateService;
        $this->logger = $logger;
    }

    /**
     * @param ProductAction $subject
     * @param Closure $proceed
     * @param array $productIds
     * @param array $attrData
     * @param int|string $storeId
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundUpdateAttributes(
        ProductAction $subject,
        Closure $proceed,
        $productIds,
        $attrData,
        $storeId
    ) {
        $targetsHiddenVisibility = isset($attrData[ProductInterface::VISIBILITY])
            && (int)$attrData[ProductInterface::VISIBILITY] === Visibility::VISIBILITY_NOT_VISIBLE;

        if (!$targetsHiddenVisibility || empty($productIds)) {
            return $proceed($productIds, $attrData, $storeId);
        }

        $idsToDiscontinue = $this->getCurrentlyVisibleProductIds($productIds, (int)$storeId);

        $result = $proceed($productIds, $attrData, $storeId);

        $this->queueDiscontinue($idsToDiscontinue, (int)$storeId);

        return $result;
    }

    /**
     * Reads, before the mass update runs, which of the given products are still
     * individually visible at the scope of this change
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return int[]
     */
    private function getCurrentlyVisibleProductIds(array $productIds, int $storeId): array
    {
        try {
            $store = $this->nostoHelperScope->getStore($storeId);
            return $this->visibilityResolver->getIndividuallyVisibleProductIds($productIds, $store);
        } catch (Exception $e) {
            $this->logger->exception($e);
            return [];
        }
    }

    /**
     * Queues a discontinue message for products that were individually visible
     * before this mass update and have just become hidden
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    private function queueDiscontinue(array $productIds, int $storeId): void
    {
        if (empty($productIds)) {
            return;
        }

        if ($storeId !== Store::DEFAULT_STORE_ID) {
            try {
                $store = $this->nostoHelperScope->getStore($storeId);
                $this->productUpdateService->addIdsToDeleteMessageQueue($productIds, $store);
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
            return;
        }

        // Default/All Store Views only changes the fallback value for stores that
        // have no override of their own, so each assigned store must be re-checked:
        // a store with its own override keeps its value regardless. Grouped and
        // queried once per store rather than once per product, since a mass update
        // can span thousands of ids and looping per product would mean a query per
        // product per store.
        //
        // This looks at post-change visibility only, so a store that was already
        // hidden by its own override before this edit is discontinued again rather
        // than skipped. Deliberate: telling the two apart would need a second
        // per-store pass before the update runs, and a repeat discontinue is a no-op
        // for a product Nosto has already dropped.
        try {
            $websiteIdsByProduct = $this->productWebsiteLink->getWebsiteIdsByProductIds($productIds);
        } catch (Exception $e) {
            $this->logger->exception($e);
            return;
        }

        $storesById = [];
        $productIdsByStoreId = [];
        foreach ($websiteIdsByProduct as $productId => $websiteIds) {
            foreach ($websiteIds as $websiteId) {
                try {
                    $stores = $this->nostoHelperScope->getWebsite($websiteId)->getStores();
                } catch (Exception $e) {
                    $this->logger->exception($e);
                    continue;
                }
                foreach ($stores as $store) {
                    $storesById[$store->getId()] = $store;
                    $productIdsByStoreId[$store->getId()][$productId] = $productId;
                }
            }
        }

        foreach ($productIdsByStoreId as $targetStoreId => $idsForStore) {
            $idsForStore = array_values($idsForStore);
            try {
                $stillVisible = $this->visibilityResolver->getIndividuallyVisibleProductIds(
                    $idsForStore,
                    $storesById[$targetStoreId]
                );
                $toDiscontinue = array_values(array_diff($idsForStore, $stillVisible));
                if (!empty($toDiscontinue)) {
                    $this->productUpdateService->addIdsToDeleteMessageQueue(
                        $toDiscontinue,
                        $storesById[$targetStoreId]
                    );
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
    }
}
