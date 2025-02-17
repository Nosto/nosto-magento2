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
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Product\Category\CategoryServiceInterface as NostoCategoryService;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Model\Category\Repository as CategoryRepository;
use Nosto\Tagging\Model\Operation\CategoryUpdateOnNosto;

class SyncService extends AbstractService
{
    public const BENCHMARK_SYNC_NAME = 'nosto_category_upsert';
    public const BENCHMARK_SYNC_BREAKPOINT = 1;

    /** @var NostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var NostoDataHelper */
    private NostoDataHelper $nostoDataHelper;

    /** @var int */
    private int $apiBatchSize;

    private CategoryRepository $categoryRepository;

    /** @var NostoCategoryService */
    private NostoCategoryService $nostoCategoryService;

    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        CategoryRepository $categoryRepository,
        NostoCategoryService $nostoCategoryService,
        $apiBatchSize,
        $apiTimeout
    ) {
        parent::__construct($nostoDataHelper, $nostoHelperAccount, $logger);
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->categoryRepository = $categoryRepository;
        $this->nostoCategoryService = $nostoCategoryService;
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
                'Nosto Category Sync is disabled',
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
            foreach ($page->getData() as $category) {
                $categoryDb = $this->nostoCategoryService->getCategory($category['entity_id'], $store);
//                $categoryDb = $this->categoryRepository->getByIds($category['entity_id']);
                $categoryIdsInBatch[] = $category['entity_id'];
                $id = $category['entity_id'];
                $name = explode("/", $categoryDb->getName());
                $name = end($name);
                $parentId = $categoryDb->getParentId();
                $urlPath = $this->getUrlPath($categoryDb, $store);
                $fullName = $categoryDb->getName();
                $available = $categoryDb->getIsActive() ?? false;
                try {
                    (new CategoryUpdateOnNosto(
                        $account,
                        $store->getCurrentUrl(),
                        $id,
                        $name,
                        $parentId,
                        $urlPath,
                        $fullName,
                        $available
                    ))->execute();
                } catch (Exception $e) {
                    $this->logger->logDebugWithStore(sprintf(
                        'Failed to update Nosto category %s with %s id',
                        $name,
                        $id
                    ));
                }
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

    private function getUrlPath($category, Store $store)
    {
        $nostoCategory = '';
        try {
            $data = [];
            $path = $category->getPath();
            $data[] = $category->getName();
            $nostoCategory = count($data) ? '/' . implode('/', $data) : '';
        } catch (Exception $e) {
            $this->logDebugWithStore($e, $store);
        }

        if (empty($nostoCategory)) {
            $nostoCategory = null;
        }

        return $nostoCategory ? trim($nostoCategory) : $nostoCategory;
    }
}
