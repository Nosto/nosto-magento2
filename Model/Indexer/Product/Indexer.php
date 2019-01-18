<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Service as ProductService;

/**
 * An indexer for Nosto product sync
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    public const BATCH_SIZE = 1000;
    public const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $dataHelper;
    private $logger;
    private $productCollectionFactory;

    /**
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        ProductService $productService,
        NostoHelperData $dataHelper,
        NostoLogger $logger,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->productService = $productService;
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
            $products = $this->getProductsIterator();
            $this->logger->logWithMemoryConsumption(
                sprintf('Indexing from executeFull')
            );
            $this->productService->update([$products]);
        } else {
            $this->logger->info('Skip full reindex since full reindex is disabled.');
        }
    }

    /**
     *
     * @return \Generator
     */
    public function getProductsIterator()
    {
        $productCollection = $this->getProductCollection();
        $productCollection->setPageSize(self::BATCH_SIZE);
        $lastPage = $productCollection->getLastPageNumber();
        $pageNumber = 0;
        do {
            $productCollection->clear();
            $productCollection->setCurPage($pageNumber);
            $productCollection->addAttributeToSelect('id')
                ->addAttributeToFilter(
                    [
                        ['attribute'=>'status','eq'=> Status::STATUS_ENABLED],
                        ['attribute'=>'visibility','neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
                    ]
                );
            // Probably we should query only the product id.
            yield from $productCollection->getItems();
            $pageNumber++;
        } while ($pageNumber <= $lastPage);
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
