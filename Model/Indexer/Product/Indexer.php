<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * An indexer for Nosto product sync
 *
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const INDEXER_ID = 'nosto_queue_products';

    protected $productService;
    protected $productCollectionFactory;
    protected $logger;

    /**
     * @param ProductService $productService
     * @param ProductCollectionFactory $productCollectionFactory
     * @internal param ProductService $context
     */
    public function __construct(
        ProductService $productService,
        ProductCollectionFactory $productCollectionFactory,
        LoggerInterface $logger

    ) {
        $this->productService = $productService;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->logger = $logger;

        $this->logger->debug('Init indexer');
    }

    public function executeFull()
    {
        $this->logger->debug('Exec full');
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToFilter('status', ['eq' => '1'])
            ->addAttributeToSelect('*');
        $collection->setPage(1,10);

        $this->productService->update($collection);

    }

    public function executeList(array $ids)
    {
        $this->logger->debug('Exec list');

        $collection = $this->productCollectionFactory->create()
            ->addAttributeToFilter('status', ['eq' => '1'])
            ->addAttributeToSelect('*');
        $collection->setPage(1,10);

        $this->productService->update($collection);

    }

    public function executeRow($id)
    {
        $this->logger->debug('Exec row');
        $this->execute([$id]);
    }

    public function execute($ids)
    {
//        $this->logger->debug('Exec');
//        $collection = $this->productRepository->create()
//            ->addAttributeToFilter('entity_id', ['in' => $ids])
//            ->addAttributeToSelect('*');

        $this->productService->addToQueue($ids);
    }
}