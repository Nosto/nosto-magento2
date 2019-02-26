<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
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
use Nosto\Tagging\Util\Memory;

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

    /**
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param NostoQueueRepository $nostoQueueRepository
     */
    public function __construct(
        ProductService $productService,
        NostoHelperData $dataHelper,
        NostoLogger $logger,
        ProductCollectionFactory $productCollectionFactory,
        NostoQueueRepository $nostoQueueRepository
    ) {
        $this->productService = $productService;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->nostoQueueRepository = $nostoQueueRepository;
    }
    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        if (!$this->dataHelper->isFullReindexEnabled()) {
            $this->logger->info('Skip full reindex since full reindex is disabled.');
            return;
        }
        // Truncate queue table before first execution if they have leftover products
        if ($this->nostoQueueRepository->isQueuePopulated()) {
            $this->nostoQueueRepository->truncate();
        }
        $productCollection = $this->getProductCollection();
        $productCollection->setPageSize(self::BATCH_SIZE);
        $lastPage = $productCollection->getLastPageNumber();
        $pageNumber = 1;
        do {
            // Stop indexing if total memory used by the script
            // is over 50% of the total available for PHP
            if (Memory::getPercentageUsedMem() > 50) {
                $this->logger->logWithMemoryConsumption(
                    'Total memory used by indexer is over 50%, exiting gracefully...'
                );
                return;
            }
            $productCollection->setCurPage($pageNumber);
            $productCollection->addAttributeToSelect('id')
                ->addAttributeToFilter(
                    [
                        ['attribute'=>'status','eq'=> Status::STATUS_ENABLED],
                        ['attribute'=>'visibility','neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
                    ]
                );
            $products = [];
            foreach ($productCollection->getItems() as $product) {
                $products[$product->getId()] = $product->getTypeId();
            }
            $productCollection->clear();
            $this->logger->logWithMemoryConsumption(
                sprintf('Indexing from executeFull, remaining pages: %d', $lastPage - $pageNumber)
            );
            $this->productService->update($products);
            $this->productService->processed = [];
            $products = null;
            $pageNumber++;
        } while ($pageNumber <= $lastPage);
        $productCollection = null;
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
        $this->logger->logWithMemoryConsumption(sprintf('Got %d ids from CL', count($ids)));
        $splitted = array_chunk(array_unique($ids), self::BATCH_SIZE);
        foreach ($splitted as $batch) {
            $this->productService->updateByIds($batch);
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
