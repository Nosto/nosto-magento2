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

namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\QueueRepository as NostoQueueRepository;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;

/**
 * An indexer for Nosto product sync
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const BATCH_SIZE = 1000;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $dataHelper;
    private $logger;
    private $productCollectionFactory;
    private $nostoQueueRepository;
    private $nostoHelperAccount;

    /**
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param NostoQueueRepository $nostoQueueRepository
     * @param NostoHelperAccount\Proxy $nostoHelperAccount
     */
    public function __construct(
        ProductService $productService,
        NostoHelperData $dataHelper,
        NostoLogger $logger,
        ProductCollectionFactory $productCollectionFactory,
        NostoQueueRepository $nostoQueueRepository,
        NostoHelperAccount\Proxy $nostoHelperAccount
    ) {
        $this->productService = $productService;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->nostoQueueRepository = $nostoQueueRepository;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        if ($this->dataHelper->isFullReindexEnabled()
            && !empty($this->nostoHelperAccount->getStoresWithNosto())
        ) {
            // Truncate queue table before first execution if they have leftover products
            if ($this->nostoQueueRepository->isQueuePopulated()) {
                $this->nostoQueueRepository->truncate();
            }
            $productCollection = $this->getProductCollection();
            $productCollection->setPageSize(self::BATCH_SIZE);
            $lastPage = $productCollection->getLastPageNumber();
            $pageNumber = 1;
            do {
                $productCollection->setCurPage($pageNumber);
                $productCollection->addAttributeToSelect('id')
                    ->addAttributeToFilter(
                        'status',
                        ['eq'=> Status::STATUS_ENABLED]
                    )->addAttributeToFilter(
                        'visibility',
                        ['neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
                    );
                $products = [];
                foreach ($productCollection->getItems() as $product) {
                    $products[$product->getId()] = $product->getTypeId();
                }
                $productCollection->clear();
                $this->logger->logWithMemoryConsumption(
                    sprintf('Indexing from executeFull, remaining pages: %d', $lastPage - $pageNumber)
                );
                try {
                    $this->productService->update($products);
                } catch (MemoryOutOfBoundsException $e) {
                    throw new LocalizedException(new Phrase($e->getMessage()));
                }
                $this->productService->processed = [];
                $products = null;
                $pageNumber++;
            } while ($pageNumber <= $lastPage);
            $productCollection = null;
        } else {
            $this->logger->info(
                'Skip full reindex since full reindex is disabled or Nosto account is not connected into any store view'
            );
        }
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute($ids)
    {
        if (!empty($this->nostoHelperAccount->getStoresWithNosto())) {
            $this->logger->logWithMemoryConsumption(sprintf('Got %d ids from CL', count($ids)));
            $splitted = array_chunk(array_unique($ids), self::BATCH_SIZE);
            foreach ($splitted as $batch) {
                $this->productService->updateByIds($batch);
            }
        } else {
            $this->logger->info(
                'Nosto account is not connected into any store view. Nothing to index.'
            );
        }
    }

    /**
     * @return ProductCollection
     */
    private function getProductCollection()
    {
        return $this->productCollectionFactory->create();
    }
}
