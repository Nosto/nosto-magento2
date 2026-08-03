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
use Magento\Catalog\Model\ResourceModel\Product\Website\Link as ProductStoreLink;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
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
    /** @var CollectionBuilder */
    private CollectionBuilder $productCollectionBuilder;

    /** @var ProductStoreLink */
    private ProductStoreLink $productStoreLink;

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * @param CollectionBuilder $productCollectionBuilder
     * @param ProductStoreLink $productStoreLink
     * @param NostoHelperScope $nostoHelperScope
     * @param ProductUpdateService $productUpdateService
     * @param NostoLogger $logger
     */
    public function __construct(
        CollectionBuilder $productCollectionBuilder,
        ProductStoreLink $productStoreLink,
        NostoHelperScope $nostoHelperScope,
        ProductUpdateService $productUpdateService,
        NostoLogger $logger
    ) {
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->productStoreLink = $productStoreLink;
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
            $collection = $this->productCollectionBuilder
                ->withStore($store)
                ->withIds($productIds)
                ->build();
            $collection->addAttributeToSelect(ProductInterface::VISIBILITY);

            $ids = [];
            foreach ($collection->getItems() as $item) {
                if ((int)$item->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE) {
                    $ids[] = (int)$item->getId();
                }
            }
            return $ids;
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

        // Default/All Store Views changes the fallback value for every store that
        // has no override of its own, so each product must be discontinued on
        // every store it is currently assigned to.
        foreach ($productIds as $productId) {
            try {
                $websiteIds = array_map(
                    'intval',
                    $this->productStoreLink->getWebsiteIdsByProductId($productId)
                );
            } catch (Exception $e) {
                $this->logger->exception($e);
                continue;
            }
            foreach ($websiteIds as $websiteId) {
                try {
                    $stores = $this->nostoHelperScope->getWebsite($websiteId)->getStores();
                    foreach ($stores as $store) {
                        $this->productUpdateService->addIdsToDeleteMessageQueue([$productId], $store);
                    }
                } catch (Exception $e) {
                    $this->logger->exception($e);
                }
            }
        }
    }
}
