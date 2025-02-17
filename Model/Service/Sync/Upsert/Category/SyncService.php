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

namespace Nosto\Tagging\Model\Service\Sync\Upsert\Category;

use Exception;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Operation\Category\CategoryUpdate;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;

class SyncService extends AbstractService
{
    public const BENCHMARK_SYNC_NAME = 'nosto_category_upsert';
    public const BENCHMARK_SYNC_BREAKPOINT = 1;

    /** @var NostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private NostoHelperUrl $nostoHelperUrl;

    /** @var CategoryBuilder */
    private CategoryBuilder $categoryBuilder;

    /** @var NostoDataHelper */
    private NostoDataHelper $nostoDataHelper;

    /** @var int */
    private int $apiBatchSize;

    /** @var int */
    private int $apiTimeout;

    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        CategoryBuilder $categoryBuilder,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        $apiBatchSize,
        $apiTimeout
    ) {
        parent::__construct($nostoDataHelper, $nostoHelperAccount, $logger);
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->apiBatchSize = $apiBatchSize;
        $this->apiTimeout = $apiTimeout;
    }

    /**
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     */
    public function sync(CategoryCollection $collection, Store $store)
    {
        if (!$this->nostoDataHelper->isProductUpdatesEnabled($store)) {
            $this->logDebugWithStore(
                'Nosto product updates are disabled',
                $store
            );
            return;
        }
        $categoryIdsInBatch = [];
        $account = $this->nostoHelperAccount->findAccount($store);
        $this->startBenchmark('nosto_category_upsert', self::BENCHMARK_SYNC_BREAKPOINT);
        $collection->setPageSize($this->apiBatchSize);
        $iterator = new PagingIterator($collection);
        /** @var CategoryCollection $page */
        foreach ($iterator as $page) {
            $this->checkMemoryConsumption('category sync');
            foreach ($page as $category) {
                // @TODO: Adjust on the SDK to batch instead of calling the API for each category
                $nostoCategory = $this->categoryBuilder->build($category, $store);
                $op = new CategoryUpdate($nostoCategory, $account, $this->nostoHelperUrl->getActiveDomain($store));
                $op->setResponseTimeout($this->apiTimeout);
                $op->execute();
            }
            $this->logDebugWithStore(
                sprintf(
                    'Nosto category sync with ids - %s',
                    implode(',', $categoryIdsInBatch)
                ),
                $store
            );
        }
    }
}
