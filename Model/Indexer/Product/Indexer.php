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

/**
 * An indexer for Nosto product sync
 *
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const BATCH_SIZE = 100;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $dataHelper;
    private $logger;

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
        NostoLogger $logger
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        $batchSize = 0;
        if ($this->dataHelper->isFullReindexEnabled()) {
            $pageNumber = 0;
            do {
                $pageNumber++;
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('status', Status::STATUS_ENABLED, 'eq')
                    ->addFilter('visibility', Visibility::VISIBILITY_NOT_VISIBLE, 'neq')
                    ->setPageSize(self::BATCH_SIZE)
                    ->setCurrentPage($pageNumber)
                    ->create();
                $products = $this->productRepository->getList($searchCriteria);
                if ($products instanceof SearchResultsInterface) {
                    $productItems = $products->getItems();
                    $batchSize += count($productItems);
                    $this->logger->logWithMemoryConsumption(
                        sprintf(
                            'Indexing %d / %d products',
                            $batchSize,
                            $products->getTotalCount()
                        )
                    );
                    $this->productService->update($productItems);
                }
            } while (($pageNumber * self::BATCH_SIZE) <= $products->getTotalCount());
        } else {
            $this->logger->info('Skip full reindex since full reindex is disabled.');
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
        $this->logger->logWithMemoryConsumption(sprintf('Got %d ids from CL', count($ids)));
        $splitted = array_chunk(array_unique($ids), self::BATCH_SIZE);
        foreach ($splitted as $batch) {
            $this->productService->updateByIds($batch);
        }
    }
}
