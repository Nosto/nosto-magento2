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

use Exception;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Update\ProductUpdateService;

/**
 * Plugin for mass website assignment changes.
 *
 * The "Update websites" mass action does not go through the product resource
 * model save, so website removals would otherwise never reach Nosto and the
 * affected products would keep being served by the accounts they left.
 */
class ProductWebsiteUpdate
{
    private const TYPE_REMOVE = 'remove';

    /** @var ProductUpdateService */
    private ProductUpdateService $productUpdateService;

    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * ProductWebsiteUpdate constructor.
     * @param ProductUpdateService $productUpdateService
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $logger
     */
    public function __construct(
        ProductUpdateService $productUpdateService,
        NostoHelperScope $nostoHelperScope,
        NostoLogger $logger
    ) {
        $this->productUpdateService = $productUpdateService;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->logger = $logger;
    }

    /**
     * Queues a discontinue message for every store view belonging to the
     * websites the products were removed from
     *
     * @param ProductAction $subject
     * @param mixed $result
     * @param array $productIds
     * @param array $websiteIds
     * @param string $type
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUpdateWebsites(
        ProductAction $subject,
        $result,
        $productIds,
        $websiteIds,
        $type
    ) {
        if ($type !== self::TYPE_REMOVE || empty($productIds) || empty($websiteIds)) {
            return $result;
        }
        try {
            foreach ($websiteIds as $websiteId) {
                $stores = $this->nostoHelperScope->getWebsite($websiteId)->getStores();
                foreach ($stores as $store) {
                    $this->productUpdateService->addIdsToDeleteMessageQueue($productIds, $store);
                }
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        return $result;
    }
}
