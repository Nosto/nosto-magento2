<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
/**
 * An indexer for Nosto product sync
 *
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const BATCH_SIZE = 1000;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $dataHelper;
    private $logger;
    private $productCollectionFactory;

    /**
     * @param ProductService $productService
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     */
    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        NostoHelperData $dataHelper,
        NostoLogger $logger,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        if ($this->dataHelper->isFullReindexEnabled()) {
            $productCollection = $this->getProductCollection();
            $productCollection->getLastPageNumber();
            // We need to get a collection here
            // then iterate and get the last page, so we can clear
            // and yield each product object without storing in memory
            $products = $this->getIterator();
                $this->logger->logWithMemoryConsumption(
                    sprintf('Indexing from executeFull')
                );
                // One by one, maybe yield in batches later on
                // The goal here is check if the memory is deallocated
                $this->productService->update([$products]);

        } else {
            $this->logger->info('Skip full reindex since full reindex is disabled.');
        }
    }

    public function getIterator()
    {
        $productCollection = $this->getProductCollection();
        $productCollection->setPageSize(self::BATCH_SIZE);
        $lastPage = $productCollection->getLastPageNumber();
        $batchSize = 0;
        $pageNumber = 0;
        do {
            $productCollection->clear();
            $productCollection->setCurPage($pageNumber);
            $productCollection->addAttributeToSelect('*')
                ->addAttributeToFilter(
                    [
                        ['attribute'=>'status','eq'=> Status::STATUS_ENABLED],
                        ['attribute'=>'visibility','neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
                    ]
                );

            foreach ($productCollection->getItems() as $key => $value) {
                yield $key => $value;
            }
            $pageNumber++;
        } while ($pageNumber <= $lastPage);
    }


    public function getProductCollection()
    {
        $collection = $this->productCollectionFactory->create();
        return $collection;
    }

    public function loadProduct($id)
    {
        $product = $this->product->create()->load($id);
        return $product;
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
}
