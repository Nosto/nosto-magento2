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

namespace Nosto\Tagging\Model\Service\Update;

use Exception;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Exception\ParentCategoryDisabledException;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Category\Repository as NostoCategoryRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\Collection as CategoryCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Model\Service\Sync\BulkPublisherInterface;

class CategoryUpdateService extends AbstractService
{
    /** @var NostoCategoryRepository $nostoCategoryRepository */
    private NostoCategoryRepository $nostoCategoryRepository;

    /** @var int $batchSize */
    private int $batchSize;

    /** @var BulkPublisherInterface */
    private BulkPublisherInterface $upsertBulkPublisher;

    /**
     * CategoryUpdateService constructor.
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     * @param NostoAccountHelper $nostoAccountHelper
     * @param NostoCategoryRepository $nostoCategoryRepository
     * @param BulkPublisherInterface $upsertBulkPublisher
     * @param int $batchSize
     */
    public function __construct(
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        NostoAccountHelper $nostoAccountHelper,
        NostoCategoryRepository $nostoCategoryRepository,
        BulkPublisherInterface $upsertBulkPublisher,
        int $batchSize
    ) {
        parent::__construct($nostoDataHelper, $nostoAccountHelper, $logger);
        $this->nostoCategoryRepository = $nostoCategoryRepository;
        $this->upsertBulkPublisher = $upsertBulkPublisher;
        $this->batchSize = $batchSize;
    }

    /**
     * Sets the categories into the message queue
     *
     * @param CategoryCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws Exception
     */
    public function addCollectionToUpdateMessageQueue(CategoryCollection $collection, Store $store)
    {
        if ($this->getAccountHelper()->findAccount($store) === null) {
            $this->logDebugWithStore('No nosto account found for the store', $store);
            return;
        }
        $collection->setPageSize($this->batchSize);
        $iterator = new PagingIterator($collection);
        $this->getLogger()->debugWithSource(
            sprintf(
                'Adding %d categories to message queue for store %s - batch size is %s, total amount of pages %d',
                $collection->getSize(),
                $store->getCode(),
                $this->batchSize,
                $iterator->getLastPageNumber()
            ),
            ['storeId' => $store->getId()],
            $this
        );
        /** @var CategoryCollection $page */
        foreach ($iterator as $page) {
            $this->upsertBulkPublisher->execute($store->getId(), $this->toParentCategoryIds($page));
        }
    }

    /**
     * @param CategoryCollection $collection
     * @return array
     */
    private function toParentCategoryIds(CategoryCollection $collection): array
    {
        $categoryIds = [];
        /** @var CategoryInterface $category */
        foreach ($collection->getItems() as $category) {
            try {
                $parents = $this->nostoCategoryRepository->resolveParentCategoryIds($category);
            } catch (ParentCategoryDisabledException $e) {
                $this->getLogger()->debug($e->getMessage());
                continue;
            }
            if (!empty($parents)) {
                foreach ($parents as $id) {
                    $categoryIds[] = $id;
                }
            } else {
                $categoryIds[] = $category->getId();
            }
        }

        return array_unique($categoryIds);
    }
}
